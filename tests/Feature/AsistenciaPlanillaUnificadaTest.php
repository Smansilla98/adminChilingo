<?php

namespace Tests\Feature;

use App\Models\Asistencia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenarios;
use Tests\TestCase;

/** La planilla web y la API usan el mismo camino de guardado: valida tipos, registra quién cargó y reconoce a todos los alumnos del bloque. */
class AsistenciaPlanillaUnificadaTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_la_planilla_web_guarda_con_autor_y_reconoce_alumnos_por_bloque_legado(): void
    {
        $sede = $this->sede('Sede Planilla');
        $bloque = $this->bloque($sede, 'Bloque Planilla');
        $pivote = $this->inscribirAlumno($this->persona('Pivote', '30111000')->fresh(), $bloque);
        $legado = $this->inscribirAlumno($this->persona('Legado', '30111001')->fresh(), $bloque);
        $legado->update(['bloque_id' => $bloque->id]);
        $admin = $this->admin('Dirección');

        $this->actingAs($admin)->post(route('asistencias.matrix.update'), [
            'bloque_id' => $bloque->id,
            'año' => 2026,
            'mes' => 3,
            'cells' => [
                $pivote->id => ['2026-03-06' => 'presente'],
                $legado->id => ['2026-03-06' => 'tarde'],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('asistencias', [
            'alumno_id' => $pivote->id, 'bloque_id' => $bloque->id, 'tipo_asistencia' => 'presente', 'registrado_por' => $admin->id,
        ]);
        $this->assertDatabaseHas('asistencias', [
            'alumno_id' => $legado->id, 'bloque_id' => $bloque->id, 'tipo_asistencia' => 'tarde', 'registrado_por' => $admin->id,
        ]);
    }

    public function test_la_planilla_web_rechaza_un_tipo_inexistente(): void
    {
        $sede = $this->sede('Sede Tipo');
        $bloque = $this->bloque($sede, 'Bloque Tipo');
        $alumno = $this->inscribirAlumno($this->persona('Tipo', '30111002')->fresh(), $bloque);
        $admin = $this->admin('Dirección');

        $this->actingAs($admin)->post(route('asistencias.matrix.update'), [
            'bloque_id' => $bloque->id,
            'año' => 2026,
            'mes' => 3,
            'cells' => [$alumno->id => ['2026-03-06' => 'inventado']],
        ])->assertSessionHasErrors();

        $this->assertSame(0, Asistencia::query()->count());
    }
}
