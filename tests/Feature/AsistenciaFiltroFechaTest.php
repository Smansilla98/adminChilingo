<?php

namespace Tests\Feature;

use App\Domain\Asistencias\VistaAsistenciaService;
use App\Models\Asistencia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenarios;
use Tests\TestCase;

/** El filtro por día del listado incluye los registros de ese día aunque tengan hora guardada. */
class AsistenciaFiltroFechaTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_el_filtro_por_dia_encuentra_registros_guardados_con_hora(): void
    {
        $sede = $this->sede('Sede Filtro');
        $bloque = $this->bloque($sede, 'Bloque Filtro');
        $alumno = $this->inscribirAlumno($this->persona('Filtro', '30777666')->fresh(), $bloque);

        Asistencia::query()->create([
            'alumno_id' => $alumno->id,
            'bloque_id' => $bloque->id,
            'fecha' => '2026-03-10 14:30:00',
            'presente' => true,
            'tipo_asistencia' => 'presente',
        ]);

        $admin = $this->admin('Dirección');

        $listado = app(VistaAsistenciaService::class)->listado($admin, $bloque->id, '2026-03-10');

        $this->assertCount(1, $listado->items());
    }
}
