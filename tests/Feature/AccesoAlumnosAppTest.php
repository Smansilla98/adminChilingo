<?php

namespace Tests\Feature;

use App\Models\Alumno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenarios;
use Tests\TestCase;

/** Cómo entran los alumnos a la app: se registran solos o administración les da una clave provisoria. */
class AccesoAlumnosAppTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function alumno(string $dni = '30123456'): Alumno
    {
        $alumno = $this->inscribirAlumno($this->persona('Ana Gómez', $dni), $this->bloque($this->sede('Saavedra')));
        $alumno->forceFill(['dni' => $dni, 'fecha_nacimiento' => '2001-05-20'])->save();

        return $alumno->fresh();
    }

    public function test_el_alumno_crea_su_cuenta_desde_la_app_y_queda_adentro(): void
    {
        $alumno = $this->alumno();

        $r = $this->postJson('/api/v1/auth/registro', [
            'dni' => '30.123.456', 'fecha_nacimiento' => '2001-05-20', 'password' => 'tambor1', 'password_confirmation' => 'tambor1',
        ])->assertCreated()->assertJsonPath('usuario', '30123456');

        $this->withToken($r->json('token'))->getJson('/api/v1/me')->assertOk()->assertJsonPath('debe_cambiar_clave', false);
        $this->assertSame((int) $alumno->persona_id, (int) User::query()->where('username', '30123456')->value('persona_id'));

        // Entra después con el DNI aunque lo escriba con puntos.
        $this->postJson('/api/v1/auth/login', ['username' => '30.123.456', 'password' => 'tambor1'])->assertOk()->assertJsonPath('debe_cambiar_clave', false);
        $this->postJson('/api/v1/auth/registro', ['dni' => '99999999', 'fecha_nacimiento' => '2001-05-20', 'password' => 'tambor1', 'password_confirmation' => 'tambor1'])
            ->assertUnprocessable()->assertJsonValidationErrors('dni');
    }

    public function test_administracion_da_acceso_con_clave_provisoria_que_hay_que_cambiar_al_entrar(): void
    {
        $alumno = $this->alumno();
        $admin = $this->admin();

        $acceso = $this->actingAs($admin)->postJson("/api/v1/personas/{$alumno->persona_id}/acceso-app")->assertOk()
            ->assertJsonPath('usuario', '30123456')->assertJsonPath('nueva', true)->json();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $acceso['clave']);
        $this->app['auth']->forgetGuards();

        $login = $this->postJson('/api/v1/auth/login', ['username' => '30123456', 'password' => $acceso['clave']])
            ->assertOk()->assertJsonPath('debe_cambiar_clave', true)->json();
        $app = fn () => $this->withToken($login['token']);
        $app()->getJson('/api/v1/me')->assertOk()->assertJsonPath('debe_cambiar_clave', true);
        $app()->getJson('/api/v1/inicio')->assertForbidden()->assertJsonPath('codigo', 'DEBE_CAMBIAR_CLAVE');

        $app()->postJson('/api/v1/auth/clave', ['actual' => $acceso['clave'], 'password' => '30123456', 'password_confirmation' => '30123456'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $app()->postJson('/api/v1/auth/clave', ['actual' => $acceso['clave'], 'password' => 'redoblante', 'password_confirmation' => 'redoblante'])->assertOk();
        $this->app['auth']->forgetGuards();

        $app()->getJson('/api/v1/inicio')->assertOk();
        $this->postJson('/api/v1/auth/login', ['username' => '30123456', 'password' => 'redoblante'])->assertOk()->assertJsonPath('debe_cambiar_clave', false);
    }

    public function test_si_se_olvido_la_clave_le_dan_una_provisoria_nueva_y_se_cierran_sus_sesiones(): void
    {
        $alumno = $this->alumno();
        $this->postJson('/api/v1/auth/registro', ['dni' => '30123456', 'fecha_nacimiento' => '2001-05-20', 'password' => 'tambor1', 'password_confirmation' => 'tambor1'])->assertCreated();
        $user = User::query()->where('username', '30123456')->sole();
        $this->assertSame(1, $user->tokens()->count());

        $this->actingAs($this->admin())->post(route('personas.acceso-app', $alumno->persona_id))
            ->assertRedirect()->assertSessionHas('acceso_app', fn ($a) => $a['usuario'] === '30123456' && $a['nueva'] === false);

        $this->assertTrue($user->fresh()->debe_cambiar_clave);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(1, User::query()->where('username', 'like', '30123456%')->count());
    }

    public function test_quien_no_administra_cuentas_no_puede_dar_acceso_ni_resetear_a_un_admin(): void
    {
        $alumno = $this->alumno();
        $this->actingAs($this->usuario('Cualquiera'))->postJson("/api/v1/personas/{$alumno->persona_id}/acceso-app")->assertForbidden();

        $otroAdmin = $this->admin('Otra admin');
        $this->actingAs($this->admin())->postJson("/api/v1/personas/{$otroAdmin->persona_id}/acceso-app")->assertUnprocessable();
    }

    public function test_en_la_web_la_clave_provisoria_obliga_a_elegir_una_propia(): void
    {
        $alumno = $this->alumno();
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('personas.show', $alumno->persona_id))->assertOk()->assertSeeText('Dar acceso a la app');
        $clave = $this->actingAs($admin)->post(route('personas.acceso-app', $alumno->persona_id))->assertSessionHas('acceso_app')->getSession()->get('acceso_app')['clave'];
        auth()->logout();

        $this->post('/login', ['username' => '30123456', 'password' => $clave]);
        $this->get(route('dashboard'))->assertRedirect(route('mi-clave.edit'));
        $this->get(route('mi-clave.edit'))->assertOk()->assertSeeText('contraseña provisoria');

        $this->put(route('mi-clave.update'), ['actual' => $clave, 'password' => 'redoblante', 'password_confirmation' => 'redoblante'])->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk();
    }
}
