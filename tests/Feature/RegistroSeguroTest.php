<?php

namespace Tests\Feature;

use App\Domain\Acceso\AjustesCuentas;
use App\Domain\Personas\RegistroAlumnoService;
use App\Models\Alumno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenarios;
use Tests\TestCase;

/** Registro de alumnos: mensaje genérico, código de alta, menores de 13 y clave de 8. */
class RegistroSeguroTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function alumno(string $dni = '30123456', string $nacimiento = '2001-05-20'): Alumno
    {
        $alumno = $this->inscribirAlumno($this->persona('Ana Gómez', $dni), $this->bloque($this->sede('Saavedra')));
        $alumno->forceFill(['dni' => $dni, 'fecha_nacimiento' => $nacimiento])->save();
        $alumno->persona->forceFill(['fecha_nacimiento' => $nacimiento])->save();

        return $alumno->fresh();
    }

    /** @return array<string, string> */
    private function datos(array $extra = []): array
    {
        return $extra + ['dni' => '30123456', 'fecha_nacimiento' => '2001-05-20', 'password' => 'tambor123', 'password_confirmation' => 'tambor123'];
    }

    public function test_no_encontrado_y_ya_tiene_cuenta_dan_el_mismo_mensaje(): void
    {
        $this->alumno();
        $noEsta = $this->postJson('/api/v1/auth/registro', $this->datos(['dni' => '99999999']))->assertUnprocessable()->json('errors.dni.0');
        $this->postJson('/api/v1/auth/registro', $this->datos())->assertCreated();
        $yaTiene = $this->postJson('/api/v1/auth/registro', $this->datos())->assertUnprocessable()->json('errors.dni.0');

        $this->assertSame(RegistroAlumnoService::NO_ENCONTRADO, $noEsta);
        $this->assertSame($noEsta, $yaTiene);
    }

    public function test_contrasena_de_menos_de_8_no_se_acepta(): void
    {
        $this->alumno();
        $this->postJson('/api/v1/auth/registro', $this->datos(['password' => 'tambor1', 'password_confirmation' => 'tambor1']))
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->post(route('registro.alumno.store'), $this->datos(['password' => 'corta12', 'password_confirmation' => 'corta12']))
            ->assertSessionHasErrors('password');
    }

    public function test_menor_de_13_no_se_registra_solo(): void
    {
        $nacimiento = now()->subYears(10)->toDateString();
        $this->alumno('45123456', $nacimiento);

        $this->postJson('/api/v1/auth/registro', $this->datos(['dni' => '45123456', 'fecha_nacimiento' => $nacimiento]))
            ->assertUnprocessable()->assertJsonPath('errors.fecha_nacimiento.0', RegistroAlumnoService::MENOR);
        $this->assertSame(0, User::query()->where('username', '45123456')->count());
    }

    public function test_codigo_de_alta_obligatorio_cuando_la_escuela_lo_activa(): void
    {
        $alumno = $this->alumno();
        $admin = $this->admin();
        AjustesCuentas::guardar(['registro_requiere_codigo' => true], $admin->id);

        $this->postJson('/api/v1/auth/registro', $this->datos())->assertUnprocessable()
            ->assertJsonPath('errors.dni.0', RegistroAlumnoService::NO_ENCONTRADO);

        $codigo = $this->actingAs($admin)->postJson("/api/v1/personas/{$alumno->persona_id}/codigo-alta")->assertCreated()->json('codigo');
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{6}$/', $codigo);
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/v1/auth/registro', $this->datos(['codigo' => 'ZZZZZZ']))->assertUnprocessable();
        $this->postJson('/api/v1/auth/registro', $this->datos(['codigo' => strtolower($codigo)]))->assertCreated();

        // Sirve una sola vez y ya no se ofrece porque tiene cuenta.
        $this->assertNotNull(\App\Models\CodigoAlta::query()->where('persona_id', $alumno->persona_id)->value('usado_at'));
        $this->actingAs($admin)->postJson("/api/v1/personas/{$alumno->persona_id}/codigo-alta")->assertUnprocessable();
    }

    public function test_el_codigo_vence_a_los_7_dias(): void
    {
        $alumno = $this->alumno();
        $admin = $this->admin();
        AjustesCuentas::guardar(['registro_requiere_codigo' => true], $admin->id);

        $this->actingAs($admin)->post(route('personas.codigo-alta', $alumno->persona_id))->assertSessionHas('codigo_alta');
        $codigo = session('codigo_alta')['codigo'];
        auth()->logout();

        $this->travel(8)->days();
        $this->postJson('/api/v1/auth/registro', $this->datos(['codigo' => $codigo]))->assertUnprocessable();
    }

    public function test_el_profe_genera_codigo_y_el_alumno_no(): void
    {
        $sede = $this->sede('Sede Código');
        $bloque = $this->bloque($sede);
        $alumno = $this->inscribirAlumno($this->persona('Bruno'), $bloque);
        $profe = $this->usuario('Profe');
        $this->asignarDocente($profe->persona, $bloque);
        $otro = $this->usuario('Otro alumno');
        $this->inscribirAlumno($otro->persona, $bloque);

        $this->actingAs($profe->fresh())->postJson("/api/v1/personas/{$alumno->persona_id}/codigo-alta")->assertCreated();
        $this->actingAs($otro->fresh())->postJson("/api/v1/personas/{$alumno->persona_id}/codigo-alta")->assertForbidden();
    }

    public function test_ajustes_de_cuentas_web(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('configuracion.cuentas.edit'))->assertOk()->assertSee('Pedir código de alta');
        $this->put(route('configuracion.cuentas.update'), ['registro_requiere_codigo' => '1'])->assertRedirect();
        $this->assertTrue(AjustesCuentas::registroRequiereCodigo());
        $this->get(route('registro.alumno'))->assertRedirect();
        auth()->logout();
        $this->get(route('registro.alumno'))->assertOk()->assertSee('Código de alta');
    }
}
