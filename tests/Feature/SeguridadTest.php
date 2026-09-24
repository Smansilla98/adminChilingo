<?php

namespace Tests\Feature;

use App\Models\ProgramaRitmo;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        config(['chilinga.edicion_publica_programa' => false]);
        $this->postJson(route('programa.toque.editor.guardar', $toque), $payload)->assertForbidden();

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
}
