<?php

namespace Tests\Feature;

use App\Domain\Operativo\CierreMesService;
use App\Models\Cuota;
use App\Models\Pago;
use App\Models\PagoDetalle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenarios;
use Tests\TestCase;

/** Cierre de marzo 2026: cobranza separada por mes de cuota (propio, arrastre, adelantado) y saldo arrastrado. Cifras a mano. */
class CierreMesCobranzaTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function cuota(int $bloqueId, int $mes, float $monto): Cuota
    {
        return Cuota::query()->create([
            'bloque_id' => $bloqueId, 'alcance' => Cuota::ALCANCE_BLOQUE, 'nombre' => 'Cuota '.$mes,
            'año' => 2026, 'mes' => $mes, 'monto' => $monto, 'activo' => true,
        ]);
    }

    private function cobro(int $alumnoId, Cuota $cuota, float $monto, string $fecha, bool $anulado = false): void
    {
        $pago = Pago::query()->create(['fecha_pago' => $fecha, 'monto_total' => $monto]);
        if ($anulado) {
            // anulado_at no es asignable en masa: se marca como lo hace el sistema al anular.
            $pago->forceFill(['anulado_at' => now()])->save();
        }
        PagoDetalle::query()->create(['pago_id' => $pago->id, 'alumno_id' => $alumnoId, 'cuota_id' => $cuota->id, 'monto' => $monto]);
    }

    public function test_cobranza_del_mes_se_separa_por_mes_de_cuota_y_muestra_el_saldo_arrastrado(): void
    {
        $bloque = $this->bloque($this->sede('Palomar'), 'Bloque Cierre');
        $ana = $this->inscribirAlumno($this->persona('Ana', '30111222')->fresh(), $bloque);
        $bea = $this->inscribirAlumno($this->persona('Bea', '30111223')->fresh(), $bloque);

        $febrero = $this->cuota($bloque->id, 2, 10000);
        $marzo = $this->cuota($bloque->id, 3, 10000);
        $abril = $this->cuota($bloque->id, 4, 10000);

        $this->cobro($ana->id, $marzo, 10000, '2026-03-05');                 // cuota del mes
        $this->cobro($ana->id, $febrero, 10500, '2026-03-15');               // arrastre, con $500 de recargo
        $this->cobro($ana->id, $abril, 2000, '2026-03-20');                  // adelantado (parcial)
        $this->cobro($ana->id, $marzo, 9999, '2026-03-21', anulado: true);   // anulado: no cuenta
        // Bea no paga febrero: queda como saldo arrastrado.

        $c = app(CierreMesService::class)->cobranza(3, 2026);

        $this->assertSame(10000.0, $c['cobrado_mes']);
        $this->assertSame(10500.0, $c['cobrado_arrastre']);
        $this->assertSame(2000.0, $c['cobrado_adelantado']);
        $this->assertSame(500.0, $c['recargo_aproximado']);
        $this->assertSame(22500.0, $c['cobrado_total']);
        $this->assertSame(10000.0, $c['saldo_arrastrado']);
        $this->assertSame(1, $c['alumnos_con_saldo']);
    }
}
