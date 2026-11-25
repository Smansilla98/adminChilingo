<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\Escenarios;
use Tests\TestCase;

/** Cada pedido de recuperación de contraseña avisa a administración, con o sin cuenta. */
class RecuperacionAvisaAdminTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function cuenta(string $dni = '30123456'): User
    {
        $persona = $this->persona('Ana', $dni);

        return User::query()->create([
            'persona_id' => $persona->id,
            'name' => 'Ana',
            'username' => $dni,
            'email' => 'ana@test.local',
            'password' => bcrypt('vieja-clave'),
            'role' => 'usuario',
            'activo' => true,
        ])->fresh();
    }

    private function avisos(User $user): array
    {
        return $user->notifications()->get()->map(fn ($n) => $n->data['titulo'] ?? '')->all();
    }

    public function test_pedido_de_una_cuenta_existente_avisa_a_los_administradores(): void
    {
        Mail::fake();
        $this->cuenta('30123456');
        $admin = $this->admin('Dirección');

        $this->postJson('/api/v1/auth/recuperar', ['usuario' => '30.123.456'])->assertOk();

        $titulos = $this->avisos($admin);
        $this->assertCount(1, $titulos);
        $this->assertStringContainsString('30.123.456', $titulos[0]); // tal como lo escribió la persona
    }

    public function test_pedido_de_un_identificador_inexistente_tambien_avisa(): void
    {
        Mail::fake();
        $admin = $this->admin('Dirección');

        $this->postJson('/api/v1/auth/recuperar', ['usuario' => '99999999'])->assertOk();

        $this->assertCount(1, $this->avisos($admin));
        $this->assertStringContainsString('99999999', $this->avisos($admin)[0]);
    }

    public function test_la_respuesta_al_solicitante_no_cambia_y_no_se_avisa_a_usuarios_comunes(): void
    {
        Mail::fake();
        $this->cuenta('30123456');
        $comun = $this->usuario('Vecina');
        $admin = $this->admin('Dirección');

        $existe = $this->postJson('/api/v1/auth/recuperar', ['usuario' => '30123456'])->assertOk()->json('mensaje');
        $noExiste = $this->postJson('/api/v1/auth/recuperar', ['usuario' => '99999999'])->assertOk()->json('mensaje');

        $this->assertSame($existe, $noExiste);
        $this->assertCount(0, $this->avisos($comun));
        $this->assertCount(2, $this->avisos($admin));
    }
}
