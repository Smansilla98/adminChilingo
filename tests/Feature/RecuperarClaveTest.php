<?php

namespace Tests\Feature;

use App\Domain\Acceso\RecuperacionClaveService;
use App\Models\RecuperacionClave;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\Escenarios;
use Tests\TestCase;

/** Recuperar la contraseña: código por correo, vencimiento, intentos y respuesta genérica. */
class RecuperarClaveTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    /** Cuenta con correo real y DNI como usuario. */
    private function cuenta(string $dni = '30123456', ?string $email = 'ana@test.local'): User
    {
        $persona = $this->persona('Ana', $dni);
        $user = User::query()->create([
            'persona_id' => $persona->id,
            'name' => 'Ana',
            'username' => $dni,
            'email' => $email ?? $dni.'@alumnos.lachilinga.invalid',
            'password' => bcrypt('vieja-clave'),
            'role' => 'usuario',
            'activo' => true,
        ]);

        return $user->fresh();
    }

    public function test_respuesta_generica_exista_o_no_la_cuenta(): void
    {
        $this->cuenta();
        $existe = $this->postJson('/api/v1/auth/recuperar', ['usuario' => '30.123.456'])->assertOk()->json('mensaje');
        $noExiste = $this->postJson('/api/v1/auth/recuperar', ['usuario' => '99999999'])->assertOk()->json('mensaje');

        $this->assertSame(RecuperacionClaveService::RESPUESTA_GENERICA, $existe);
        $this->assertSame($existe, $noExiste);
    }

    public function test_manda_codigo_por_correo_y_permite_cambiar_la_clave(): void
    {
        Mail::fake();
        $user = $this->cuenta();
        $user->createToken('celu');

        $this->postJson('/api/v1/auth/recuperar', ['usuario' => '30123456'])->assertOk();

        // El código va por correo, no en la respuesta; para probar la confirmación fijamos uno conocido.
        $registro = RecuperacionClave::query()->where('user_id', $user->id)->sole();
        $this->assertSame('email', $registro->canal);
        $this->assertNull($registro->usado_at);

        // Fuerza un código conocido para probar la confirmación.
        $registro->forceFill(['codigo_hash' => RecuperacionClaveService::hash('482913')])->save();
        $this->postJson('/api/v1/auth/recuperar/confirmar', [
            'usuario' => '30123456', 'codigo' => '482913', 'password' => 'nueva-clave-1', 'password_confirmation' => 'nueva-clave-1',
        ])->assertOk();

        $this->assertSame(0, $user->tokens()->count(), 'se cierran las sesiones');
        $this->postJson('/api/v1/auth/login', ['username' => '30123456', 'password' => 'nueva-clave-1'])->assertOk();
    }

    public function test_el_codigo_sirve_una_sola_vez(): void
    {
        $user = $this->cuenta();
        $user->recuperacionesClave()->create([
            'canal' => 'email', 'codigo_hash' => RecuperacionClaveService::hash('111222'), 'vence_at' => now()->addMinutes(15),
        ]);
        $datos = ['usuario' => '30123456', 'codigo' => '111222', 'password' => 'nueva-clave-1', 'password_confirmation' => 'nueva-clave-1'];

        $this->postJson('/api/v1/auth/recuperar/confirmar', $datos)->assertOk();
        $this->postJson('/api/v1/auth/recuperar/confirmar', $datos)->assertUnprocessable()->assertJsonValidationErrors('codigo');
    }

    public function test_el_codigo_vence_a_los_15_minutos(): void
    {
        $user = $this->cuenta();
        $user->recuperacionesClave()->create([
            'canal' => 'email', 'codigo_hash' => RecuperacionClaveService::hash('333444'), 'vence_at' => now()->addMinutes(15),
        ]);

        $this->travel(16)->minutes();
        $this->postJson('/api/v1/auth/recuperar/confirmar', [
            'usuario' => '30123456', 'codigo' => '333444', 'password' => 'nueva-clave-1', 'password_confirmation' => 'nueva-clave-1',
        ])->assertUnprocessable()->assertJsonValidationErrors('codigo');
    }

    public function test_cinco_intentos_malos_bloquean_el_codigo(): void
    {
        $user = $this->cuenta();
        $registro = $user->recuperacionesClave()->create([
            'canal' => 'email', 'codigo_hash' => RecuperacionClaveService::hash('555666'), 'vence_at' => now()->addMinutes(15),
        ]);
        $malo = ['usuario' => '30123456', 'codigo' => '000000', 'password' => 'nueva-clave-1', 'password_confirmation' => 'nueva-clave-1'];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/recuperar/confirmar', $malo)->assertUnprocessable();
        }
        // Aunque ahora acierte, el código ya no sirve (y el limitador corta los intentos seguidos).
        $this->postJson('/api/v1/auth/recuperar/confirmar', array_merge($malo, ['codigo' => '555666']))->assertStatus(429);
        $this->assertNull($registro->fresh()->usado_at);
        $this->assertSame(5, $registro->fresh()->intentos);
    }

    public function test_cuenta_sin_correo_real_ni_whatsapp_no_recibe_codigo(): void
    {
        $user = $this->cuenta('31123456', null);
        $this->postJson('/api/v1/auth/recuperar', ['usuario' => '31123456'])->assertOk();
        $this->assertSame(0, $user->recuperacionesClave()->count());
    }

    public function test_no_se_puede_usar_el_dni_como_clave_nueva(): void
    {
        $user = $this->cuenta();
        $user->recuperacionesClave()->create([
            'canal' => 'email', 'codigo_hash' => RecuperacionClaveService::hash('777888'), 'vence_at' => now()->addMinutes(15),
        ]);

        $this->postJson('/api/v1/auth/recuperar/confirmar', [
            'usuario' => '30123456', 'codigo' => '777888', 'password' => '30123456', 'password_confirmation' => '30123456',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_web_mismo_mensaje_y_confirmacion(): void
    {
        $this->cuenta();
        $this->post(route('recuperar.store'), ['usuario' => 'nadie'])->assertRedirect()->assertSessionHas('info', RecuperacionClaveService::RESPUESTA_GENERICA);
        $this->post(route('recuperar.store'), ['usuario' => '30123456'])->assertSessionHas('info', RecuperacionClaveService::RESPUESTA_GENERICA);

        $user = User::query()->where('username', '30123456')->sole();
        $user->recuperacionesClave()->update(['codigo_hash' => RecuperacionClaveService::hash('121212')]);
        $this->post(route('recuperar.confirmar.store'), [
            'usuario' => '30.123.456', 'codigo' => '121212', 'password' => 'nueva-clave-1', 'password_confirmation' => 'nueva-clave-1',
        ])->assertRedirect(route('login'));
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('nueva-clave-1', $user->fresh()->password));
    }

    public function test_la_pantalla_de_login_tiene_el_enlace(): void
    {
        $this->get(route('login'))->assertOk()->assertSee(route('recuperar.create'), false);
        $this->get(route('recuperar.create'))->assertOk()->assertSee('Olvidé mi contraseña');
    }
}
