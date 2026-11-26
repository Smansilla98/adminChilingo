<?php

namespace Tests\Feature;

use App\Models\Cuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenarios;
use Tests\TestCase;

/**
 * Regresión de la ficha del alumno: estado de cuenta (cuotas que aplican, vencidas y pendientes).
 */
class AlumnoEstadoCuentaTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_la_ficha_muestra_las_cuotas_del_bloque_con_su_estado(): void
    {
        $sede = $this->sede('Centro');
        $bloque = $this->bloque($sede, 'Trinchera');
        $otro = $this->bloque($sede, 'Otro');
        $alumno = $this->inscribirAlumno($this->persona('Ana Cuenta', '30999111'), $bloque);

        $vencida = Cuota::query()->create(['bloque_id' => $bloque->id, 'alcance' => Cuota::ALCANCE_BLOQUE, 'nombre' => 'Marzo', 'año' => 2026, 'mes' => 3, 'fecha_vencimiento' => '2026-03-10', 'monto' => 5000, 'activo' => true]);
        $pendiente = Cuota::query()->create(['bloque_id' => $bloque->id, 'alcance' => Cuota::ALCANCE_BLOQUE, 'nombre' => 'Diciembre', 'año' => 2027, 'mes' => 12, 'fecha_vencimiento' => '2027-12-10', 'monto' => 7000, 'activo' => true]);
        Cuota::query()->create(['bloque_id' => $otro->id, 'alcance' => Cuota::ALCANCE_BLOQUE, 'nombre' => 'Ajena', 'año' => 2027, 'mes' => 12, 'fecha_vencimiento' => '2027-12-10', 'monto' => 1, 'activo' => true]);

        $this->actingAs($this->admin())->get(route('alumnos.show', $alumno))
            ->assertOk()
            ->assertViewHas('estadoCuenta', function ($cuenta) use ($vencida, $pendiente) {
                $porCuota = collect($cuenta)->keyBy(fn ($fila) => $fila['cuota']->id);

                return $cuenta->count() === 2
                    && $porCuota[$vencida->id]['estado'] === 'Vencida'
                    && $porCuota[$vencida->id]['periodo'] === '03/2026'
                    && $porCuota[$pendiente->id]['estado'] === 'Pendiente'
                    && $porCuota[$pendiente->id]['monto'] === 7000.0;
            })
            ->assertViewHas('historialPagos', fn ($h) => $h->isEmpty());
    }
}
