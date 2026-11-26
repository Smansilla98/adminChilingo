<?php

namespace Tests\Feature\Auditoria;

use App\Models\VillaGesellGasto;
use App\Models\VillaGesellInscripto;
use App\Models\VillaGesellInsumo;
use App\Services\VillaGesellGiraService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenarios;
use Tests\TestCase;

/**
 * Auditoría Villa Gesell. Gira del 16/01 al 14/02/2027 = 30 días. Aporte por día $500. Cupo 40.
 *
 *  - A: plaza 1, 16/01 al 20/01 (5 días) → esperado $2.500, pagó (seña) $1.000 → saldo $1.500.
 *  - B: plaza 2, gira completa (30 días) → esperado $15.000, pagó $15.000 → saldo $0.
 *  - C: lista de espera, gira completa → esperado $15.000, pagó $0. No ocupa plaza.
 *  Ingresos pagados: $16.000. Ingresos esperados (plazas, sin lista de espera): $17.500.
 *  Gastos: fijo $3.000 (valor único) + diario $100 × 30 días = $3.000 → $6.000. Insumos: 4 × $250 = $1.000.
 *  Egresos: $7.000. Balance pagado $9.000. Balance esperado $10.500. Cupo lleno: 500 × 30 × 40 = $600.000.
 */
class VillaGesellAuditoriaTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function escenario(): void
    {
        $gira = app(VillaGesellGiraService::class);
        $gira->config()->update(['aporte_esperado' => 500, 'fecha_inicio' => '2027-01-16', 'fecha_fin' => '2027-02-14', 'cupo_maximo' => 40]);
        $sede = $this->sede('Gesell');
        $bloque = $this->bloque($sede, 'Gira');
        $a = $this->inscribirAlumno($this->persona('Alumna A'), $bloque);
        $b = $this->inscribirAlumno($this->persona('Alumno B'), $bloque);
        $c = $this->inscribirAlumno($this->persona('Alumno C'), $bloque);

        VillaGesellInscripto::query()->create(['alumno_id' => $a->id, 'estado_pago' => 'sena', 'plaza' => 1, 'lista_espera' => false,
            'fecha_desde' => '2027-01-16', 'fecha_hasta' => '2027-01-20', 'monto_esperado' => 2500, 'monto_pagado' => 1000]);
        VillaGesellInscripto::query()->create(['alumno_id' => $b->id, 'estado_pago' => 'pagado', 'plaza' => 2, 'lista_espera' => false,
            'fecha_desde' => '2027-01-16', 'fecha_hasta' => '2027-02-14', 'monto_esperado' => 15000, 'monto_pagado' => 15000]);
        VillaGesellInscripto::query()->create(['alumno_id' => $c->id, 'estado_pago' => 'sena', 'plaza' => null, 'lista_espera' => true,
            'fecha_desde' => '2027-01-16', 'fecha_hasta' => '2027-02-14', 'monto_esperado' => 15000, 'monto_pagado' => 0]);

        VillaGesellGasto::query()->create(['tipo' => 'fijo', 'concepto' => 'Alquiler casa', 'monto' => 3000, 'modo' => 'total']);
        VillaGesellGasto::query()->create(['tipo' => 'diario', 'concepto' => 'Comida', 'monto' => 100, 'modo' => 'por_dia']);
        VillaGesellInsumo::query()->create(['nombre' => 'Parches', 'categoria' => 'otro', 'cantidad' => 4, 'unidad' => 'u', 'costo_unitario' => 250]);
    }

    public function test_saldo_por_inscripto_y_aporte_por_dias(): void
    {
        $this->escenario();
        $a = VillaGesellInscripto::query()->where('plaza', 1)->firstOrFail();
        $this->assertSame(5, $a->diasUtilizados());
        $this->assertSame(2500.0, $a->aporteSegunDias(500.0));
        $this->assertSame(1500.0, $a->saldo());
        $this->assertSame(0.0, VillaGesellInscripto::query()->where('plaza', 2)->firstOrFail()->saldo());
    }

    public function test_plan_de_gastos_con_cifras_exactas(): void
    {
        $this->escenario();
        $plan = app(VillaGesellGiraService::class)->plan();

        $this->assertSame(30, $plan['dias']);
        $this->assertSame(2, $plan['plazas_ocupadas']);
        $this->assertSame(1, $plan['lista_espera']);
        $this->assertEquals(16000.0, $plan['ingresos_pagados']);
        $this->assertEquals(6000.0, $plan['gastos_totales']);
        $this->assertEquals(1000.0, $plan['insumos_totales']);
        $this->assertEquals(9000.0, $plan['balance_pagado']);
        $this->assertEquals(600000.0, $plan['ingresos_si_cupo_lleno']);
    }

    public function test_ingresos_esperados_no_incluyen_lista_de_espera(): void
    {
        $this->escenario();
        $plan = app(VillaGesellGiraService::class)->plan();

        // Esperado: solo quienes ocupan plaza (A y B). La lista de espera no es ingreso previsto.
        $this->assertEquals(17500.0, $plan['ingresos_esperados'], 'Ingresos esperados = plazas A + B');
        $this->assertEquals(10500.0, $plan['balance_esperado']);
    }
}
