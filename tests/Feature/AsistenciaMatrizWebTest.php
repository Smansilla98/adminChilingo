<?php

namespace Tests\Feature;

use App\Models\Asistencia;
use App\Models\Bloque;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenarios;
use Tests\TestCase;

/**
 * Regresión de las pantallas web de asistencias: matriz mensual, guardado de la matriz y listado.
 */
class AsistenciaMatrizWebTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function escenario(): array
    {
        $sede = $this->sede('Centro');
        $bloque = $this->bloque($sede, 'Trinchera');
        $ana = $this->inscribirAlumno($this->persona('Ana Test', '30111222'), $bloque);
        $luis = $this->inscribirAlumno($this->persona('Luis Test', '30111333'), $bloque);

        return [$this->admin(), $bloque, $ana, $luis];
    }

    public function test_la_matriz_muestra_las_fechas_de_clase_del_mes(): void
    {
        [$admin, $bloque] = $this->escenario();

        // Marzo 2026 sin horarios cargados: viernes 6, 13, 20 y 27.
        $this->actingAs($admin)->get(route('asistencias.index', ['bloque_id' => $bloque->id, 'mes' => 3, 'año' => 2026]))
            ->assertOk()
            ->assertViewHas('matrix', true)
            ->assertViewHas('matrixUpdateRoute', 'asistencias.matrix.update')
            ->assertViewHas('fechas', fn ($fechas) => $fechas->map->format('Y-m-d')->all() === ['2026-03-06', '2026-03-13', '2026-03-20', '2026-03-27'])
            ->assertViewHas('alumnos', fn ($alumnos) => $alumnos->count() === 2);
    }

    public function test_guardar_matriz_registra_celdas_validas_y_borra_las_vacias(): void
    {
        [$admin, $bloque, $ana, $luis] = $this->escenario();

        $this->actingAs($admin)->post(route('asistencias.matrix.update'), [
            'bloque_id' => $bloque->id,
            'mes' => 3,
            'año' => 2026,
            'cells' => [
                $ana->id => ['2026-03-06' => 'presente', '2026-03-13' => 'ausencia_justificada'],
                // Sábado 7: no es día de clase del bloque, se ignora.
                $luis->id => ['2026-03-07' => 'presente'],
            ],
        ])->assertRedirect(route('asistencias.index', ['bloque_id' => $bloque->id, 'mes' => 3, 'año' => 2026]))
            ->assertSessionHas('success', 'Matriz de asistencias guardada.');

        $this->assertTrue(Asistencia::query()->where('alumno_id', $ana->id)->whereDate('fecha', '2026-03-06')->where('tipo_asistencia', 'presente')->where('presente', true)->exists());
        $this->assertTrue(Asistencia::query()->where('alumno_id', $ana->id)->whereDate('fecha', '2026-03-13')->where('tipo_asistencia', 'ausencia_justificada')->exists());
        $this->assertDatabaseMissing('asistencias', ['alumno_id' => $luis->id]);
        $this->assertSame(2, Asistencia::query()->count());

        $this->actingAs($admin)->post(route('asistencias.matrix.update'), [
            'bloque_id' => $bloque->id,
            'mes' => 3,
            'año' => 2026,
            'cells' => [$ana->id => ['2026-03-06' => '']],
        ])->assertRedirect();

        $this->assertFalse(Asistencia::query()->where('alumno_id', $ana->id)->whereDate('fecha', '2026-03-06')->exists());
        $this->assertTrue(Asistencia::query()->where('alumno_id', $ana->id)->whereDate('fecha', '2026-03-13')->exists());
    }

    public function test_listado_muestra_las_asistencias_del_bloque(): void
    {
        [$admin, $bloque, $ana] = $this->escenario();
        Asistencia::query()->create(['alumno_id' => $ana->id, 'bloque_id' => $bloque->id, 'fecha' => '2026-03-06', 'tipo_asistencia' => 'presente', 'presente' => true]);
        Asistencia::query()->create(['alumno_id' => $ana->id, 'bloque_id' => $bloque->id, 'fecha' => '2026-03-13', 'tipo_asistencia' => 'presente', 'presente' => true]);

        $this->actingAs($admin)->get(route('asistencias.index', ['vista' => 'lista', 'bloque_id' => $bloque->id]))
            ->assertOk()
            ->assertViewHas('vistaLista', true)
            ->assertViewHas('asistencias', fn ($p) => $p->total() === 2);
    }
}
