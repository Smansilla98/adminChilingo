<?php

namespace Tests\Feature;

use App\Models\BibliotecaItem;
use App\Models\ProgramaRitmo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

class SeguridadTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_login_web_y_api_limitan_intentos(): void
    {
        $user = $this->usuario('Ana');
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['username' => $user->username, 'password' => 'mal']);
        }
        $this->post('/login', ['username' => $user->username, 'password' => 'mal'])->assertStatus(429);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['username' => 'otro', 'password' => 'mal']);
        }
        $this->postJson('/api/v1/auth/login', ['username' => 'otro', 'password' => 'mal'])->assertStatus(429);
    }

    public function test_respuestas_incluyen_cabeceras_de_seguridad(): void
    {
        $this->get('/login')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_edicion_publica_de_partituras_se_puede_cerrar(): void
    {
        $toque = ProgramaRitmo::query()->firstOrFail();
        $payload = ['editor_nombre' => 'Visitante', 'score' => ['title' => 'x']];

        // Cerrada por defecto.
        $this->assertFalse(config('chilinga.edicion_publica_programa'));
        $this->postJson(route('programa.toque.editor.guardar', $toque), $payload)->assertUnauthorized();
        $this->actingAs($this->usuario('Visitante'))->postJson(route('programa.toque.editor.guardar', $toque), $payload)->assertForbidden();

        // Con el permiso de administración del programa sí se puede.
        $this->actingAs($this->admin())->postJson(route('programa.toque.editor.guardar', $toque), $payload)->assertStatus(422);
    }

    public function test_login_no_asigna_roles_por_defecto(): void
    {
        $user = $this->usuario('Sin rol');
        $this->post('/login', ['username' => $user->username, 'password' => 'password'])->assertRedirect(route('dashboard'));

        $this->assertSame([], $user->fresh()->getRoleNames()->all());
        $this->assertSame([], $user->fresh()->acceso()->permisos());
    }

    public function test_administrador_no_crea_cuentas_de_direccion_por_el_formulario_heredado(): void
    {
        $admin = $this->admin();
        $datos = [
            'name' => 'Nuevo', 'username' => 'nuevo-admin', 'email' => 'nuevo@test.local',
            'password' => 'secreto123', 'password_confirmation' => 'secreto123',
            'role' => 'admin', 'profesor_vinculo' => 'ninguno',
        ];

        $this->actingAs($admin)->post(route('accesos.store'), $datos)->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['username' => 'nuevo-admin']);

        $super = $this->usuario('Super');
        $this->asignarRol($super->persona, 'superadministrador');
        $this->actingAs($super->fresh())->post(route('accesos.store'), $datos)->assertSessionHasNoErrors();
        $this->assertTrue(\App\Models\User::query()->where('username', 'nuevo-admin')->firstOrFail()->isAdmin());
    }

    public function test_el_login_cuenta_igual_el_dni_con_o_sin_puntos(): void
    {
        $user = $this->usuario('Ana');
        $user->update(['username' => '30123456']);
        foreach (['30.123.456', '30123456 ', '30-123-456', '30 123 456', '30123456'] as $variante) {
            $this->postJson('/api/v1/auth/login', ['username' => $variante, 'password' => 'mal']);
        }
        $this->postJson('/api/v1/auth/login', ['username' => '30.123.456', 'password' => 'mal'])->assertStatus(429);
    }

    public function test_biblioteca_rechaza_un_archivo_disfrazado_y_sirve_con_tipo_fijo(): void
    {
        Storage::fake('comprobantes');
        $tmp = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($tmp, '<!DOCTYPE html><html><body><script>alert(1)</script></body></html>');
        $html = new UploadedFile($tmp, 'apunte.pdf', 'application/pdf', null, true);

        $this->post(route('biblioteca.store'), ['titulo' => 'Apunte', 'archivo' => $html])
            ->assertSessionHasErrors('archivo');
        $this->assertSame(0, BibliotecaItem::query()->count());

        // Un archivo viejo guardado con un tipo peligroso se sirve igual con el de su extensión y sin poder ejecutar nada.
        Storage::disk('comprobantes')->put('biblioteca/x.png', '<svg onload="alert(1)"></svg>');
        $item = BibliotecaItem::query()->create(['titulo' => 'Viejo', 'tipo' => 'imagen', 'path' => 'biblioteca/x.png', 'mime' => 'image/svg+xml', 'estado' => 'publicado']);
        $r = $this->get(route('biblioteca.archivo', $item))->assertOk();
        $this->assertSame('image/png', $r->headers->get('Content-Type'));
        $this->assertSame('nosniff', $r->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('sandbox', (string) $r->headers->get('Content-Security-Policy'));

        Storage::disk('comprobantes')->put('biblioteca/y.html', '<script>alert(1)</script>');
        $otro = BibliotecaItem::query()->create(['titulo' => 'Raro', 'tipo' => 'otro', 'path' => 'biblioteca/y.html', 'mime' => 'text/html', 'estado' => 'publicado']);
        $r = $this->get(route('biblioteca.archivo', $otro))->assertOk();
        $this->assertSame('application/octet-stream', $r->headers->get('Content-Type'));
        $this->assertStringStartsWith('attachment', (string) $r->headers->get('Content-Disposition'));
    }

    public function test_las_versiones_de_partitura_piden_permiso_de_ver_partituras(): void
    {
        $toque = ProgramaRitmo::query()->firstOrFail();
        Sanctum::actingAs($this->usuario('Sin permisos'));

        $this->getJson("/api/v1/partituras/{$toque->slug}/versiones")->assertForbidden();
        $this->getJson("/api/v1/partituras/{$toque->slug}/versiones/1")->assertForbidden();
    }
}
