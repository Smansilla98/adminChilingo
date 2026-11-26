<?php

namespace Tests\Feature\Auditoria;

use App\Domain\Finanzas\RegistrarPago;
use App\Domain\Reportes\ReportesService;
use App\Models\Cuota;
use App\Models\Gasto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenarios;
use Tests\TestCase;

/**
 * Auditoría de totales: el resultado financiero del mes = cobrado − gastos. Un gasto rechazado
 * no es un gasto: no debe restar.
 */
class TotalesGastosAuditoriaTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_resultado_financiero_no_resta_gastos_rechazados(): void
    {
        $admin = $this->admin('Tesorería');
        $sede = $this->sede('Palomar');
        $alumno = $this->inscribirAlumno($this->persona('Carla Auditoría'), $this->bloque($sede, 'Martes'));
        $cuota = Cuota::query()->create([
            'nombre' => 'Mensual', 'año' => (int) now()->year, 'mes' => (int) now()->month, 'monto' => 6000,
            'alcance' => 'bloque', 'bloque_id' => $alumno->bloque_id, 'activo' => true,
            'fecha_vencimiento' => now()->addDays(10)->toDateString(),
        ]);
        app(RegistrarPago::class)->ejecutar([
            'fecha_pago' => now()->toDateString(),
            'lineas' => [['alumno_id' => $alumno->id, 'cuota_id' => $cuota->id, 'monto' => 6000]],
            'monto_total' => 6000, 'liquidar_profesor' => '0',
        ], $admin);

        // Gastos: aprobado 3.000 (admin aprueba al cargar), pendiente 2.000, rechazado 5.000.
        Gasto::query()->create(['sede_id' => $sede->id, 'fecha' => now()->toDateString(), 'tipo' => 'insumo', 'monto' => 3000, 'created_by' => $admin->id])
            ->forceFill(['estado' => 'aprobado'])->save();
        Gasto::query()->create(['sede_id' => $sede->id, 'fecha' => now()->toDateString(), 'tipo' => 'luz', 'monto' => 2000, 'created_by' => $admin->id])
            ->forceFill(['estado' => 'pendiente'])->save();
        Gasto::query()->create(['sede_id' => $sede->id, 'fecha' => now()->toDateString(), 'tipo' => 'reparacion', 'monto' => 5000, 'created_by' => $admin->id])
            ->forceFill(['estado' => 'rechazado'])->save();

        $r = app(ReportesService::class)->compilar((int) now()->month, (int) now()->year, $admin);

        $this->assertSame(6000.0, (float) $r['ingresosTotales'], 'Ingresos = cobrado vigente');
        // Esperado: aprobado + pendiente = 5.000 (el rechazado no es gasto). Resultado 1.000.
        $this->assertSame(5000.0, (float) $r['gastosTotales'], 'El gasto rechazado de 5.000 no debe sumarse a gastos');
        $this->assertSame(1000.0, (float) $r['resultadoGlobal']);
    }
}
