<?php

namespace Tests\Feature;

use App\Domain\Personas\CicloLectivoService;
use App\Models\Alumno;
use App\Models\CicloLectivo;
use App\Models\Reinscripcion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

/** Ciclo lectivo y reinscripción anual: no rompe cuotas ni asistencias existentes. */
class CicloLectivoTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    /** Alumno activo con cuenta propia. */
    private function alumnoConCuenta(string $nombre, bool $activo = true): array
    {
        $sede = $this->sede('Sede Ciclo');
        $bloque = $this->bloque($sede, 'Bloque Ciclo');
        $user = $this->usuario($nombre);
        $alumno = $this->inscribirAlumno($user->persona, $bloque);
        $alumno->forceFill(['user_id' => $user->id, 'activo' => $activo])->save();

        return [$alumno->fresh(), $user->fresh(), $bloque];
    }

    private function abrir(int $anio = 2027): CicloLectivo
    {
        return app(CicloLectivoService::class)->abrir([
            'anio' => $anio, 'nombre' => null, 'inicio' => $anio.'-03-01', 'fin' => $anio.'-12-15',
        ], $this->admin());
    }

    public function test_abrir_el_ciclo_crea_una_reinscripcion_por_alumno_activo_y_avisa(): void
    {
        [$activo] = $this->alumnoConCuenta('Ana');
        [$inactivo] = $this->alumnoConCuenta('Beto', false);

        $ciclo = $this->abrir();

        $this->assertSame(1, $ciclo->reinscripciones()->count());
        $this->assertSame(Reinscripcion::PENDIENTE, $ciclo->reinscripciones()->sole()->estado);
        $this->assertSame($activo->id, $ciclo->reinscripciones()->sole()->alumno_id);
        $this->assertSame(1, app(CicloLectivoService::class)->avisar($ciclo));
        $this->assertNotSame($inactivo->id, $ciclo->reinscripciones()->sole()->alumno_id);
    }

    public function test_el_alumno_se_reinscribe_y_no_se_borra_nada(): void
    {
        [$alumno, $user, $bloque] = $this->alumnoConCuenta('Ana');
        $this->abrir();

        $this->actingAs($user)->post(route('mi-reinscripcion.confirmar'))->assertRedirect(route('mi-reinscripcion.show'));

        $this->assertSame(Reinscripcion::CONFIRMADA, Reinscripcion::query()->where('alumno_id', $alumno->id)->value('estado'));
        $this->assertTrue(Alumno::query()->whereKey($alumno->id)->where('activo', true)->exists());
        $this->assertTrue($alumno->bloques()->where('bloques.id', $bloque->id)->exists(), 'la inscripción al bloque sigue');
    }

    public function test_dar_de_baja_a_quien_no_se_reinscribio_sin_borrar_su_historial(): void
    {
        [$alumno, , $bloque] = $this->alumnoConCuenta('Ana');
        $admin = $this->admin();
        $this->abrir();
        $r = Reinscripcion::query()->where('alumno_id', $alumno->id)->sole();

        $this->actingAs($admin)->post(route('reinscripciones.baja', $r))->assertRedirect();

        $this->assertSame(Reinscripcion::BAJA, $r->fresh()->estado);
        $this->assertFalse((bool) $alumno->fresh()->activo);
        $this->assertTrue(Alumno::query()->whereKey($alumno->id)->exists(), 'el alumno no se borra');
        $this->assertTrue($alumno->bloques()->where('bloques.id', $bloque->id)->exists(), 'el historial de bloques queda');
    }

    public function test_no_se_da_de_baja_a_quien_ya_se_reinscribio(): void
    {
        [$alumno, $user] = $this->alumnoConCuenta('Ana');
        $this->abrir();
        $this->actingAs($user)->post(route('mi-reinscripcion.confirmar'));
        $r = Reinscripcion::query()->where('alumno_id', $alumno->id)->sole();

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(CicloLectivoService::class)->darDeBaja($r, $this->admin());
    }

    public function test_no_se_abre_dos_veces_el_mismo_anio(): void
    {
        $this->abrir(2028);
        $this->actingAs($this->admin())->post(route('ciclos.store'), ['anio' => 2028, 'inicio' => '2028-03-01', 'fin' => '2028-12-15'])
            ->assertSessionHasErrors('anio');
    }

    public function test_sin_permiso_no_se_abre_el_ciclo(): void
    {
        [, $user] = $this->alumnoConCuenta('Ana');
        $this->actingAs($user)->post(route('ciclos.store'), ['anio' => 2030, 'inicio' => '2030-03-01', 'fin' => '2030-12-15'])->assertForbidden();
        $this->actingAs($user)->get(route('ciclos.index'))->assertForbidden();
        $this->assertSame(0, CicloLectivo::query()->count());
    }

    public function test_api_del_alumno_y_de_administracion(): void
    {
        [$alumno, $user] = $this->alumnoConCuenta('Ana');
        $this->abrir();

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/mi/reinscripcion')->assertOk()
            ->assertJsonPath('ciclo.anio', 2027)->assertJsonPath('alumnos.0.estado', Reinscripcion::PENDIENTE);
        $this->postJson('/api/v1/mi/reinscripcion')->assertOk()->assertJsonPath('confirmadas', 1);

        Sanctum::actingAs($this->admin());
        $this->getJson('/api/v1/ciclos-lectivos/'.CicloLectivo::query()->value('id').'/reinscripciones')
            ->assertOk()->assertJsonPath('resumen.confirmadas', 1)->assertJsonPath('data.0.alumno', 'Ana');
    }

    public function test_el_resumen_cuenta_por_estado(): void
    {
        $this->alumnoConCuenta('Ana');
        $this->alumnoConCuenta('Beto');
        $ciclo = $this->abrir();
        $r = $ciclo->reinscripciones()->first();
        app(CicloLectivoService::class)->darDeBaja($r, $this->admin());

        $this->assertSame(['total' => 2, 'confirmadas' => 0, 'pendientes' => 1, 'bajas' => 1], app(CicloLectivoService::class)->resumen($ciclo));
    }
}
