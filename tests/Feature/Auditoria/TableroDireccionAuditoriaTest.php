<?php

namespace Tests\Feature\Auditoria;

use App\Domain\Finanzas\RegistrarPago;
use App\Domain\Reportes\TableroDireccionService;
use App\Models\Alumno;
use App\Models\Cuota;
use App\Models\Gasto;
use App\Models\Pago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenarios;
use Tests\TestCase;

/**
 * Auditoría del tablero de dirección, mes en curso. Cifras a mano:
 *  - Bloque con 3 alumnos activos y 1 inactivo. Cuota del mes activa de $10.000 → emitido = 3 × 10.000 = $30.000.
 *  - Una cuota del mismo mes desactivada (reemplazada) de $10.000: NO debe emitir.
 *  - Cobrado: $10.000 (pago de un alumno). Pago anulado de $3.000: no cuenta.
 *  - Gastos: aprobado $3.000; rechazado $5.000: no cuenta. Resultado $7.000, margen 70 %.
 *  - Cobranza = 10.000 / 30.000 = 33,3 %.
 */
class TableroDireccionAuditoriaTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_cifras_del_mes_en_curso_cierran_a_mano(): void
    {
        $admin = $this->admin('Dirección');
        $bloque = $this->bloque($this->sede('Palomar'), 'Martes');
        $alumnos = collect(['Ana', 'Beto', 'Cami'])->map(fn ($n) => $this->inscribirAlumno($this->persona($n), $bloque));
        $inactivo = $this->inscribirAlumno($this->persona('Dani'), $bloque);
        $inactivo->update(['activo' => false]);

        $mes = (int) now()->month;
        $año = (int) now()->year;
        Cuota::query()->create(['nombre' => 'Mensual', 'año' => $año, 'mes' => $mes, 'monto' => 10000, 'alcance' => 'bloque', 'bloque_id' => $bloque->id, 'activo' => true, 'fecha_vencimiento' => now()->addDays(10)->toDateString()]);
        Cuota::query()->create(['nombre' => 'Mensual (reemplazada)', 'año' => $año, 'mes' => $mes, 'monto' => 10000, 'alcance' => 'bloque', 'bloque_id' => $bloque->id, 'activo' => false, 'fecha_vencimiento' => now()->addDays(10)->toDateString()]);
        $cuotaActiva = Cuota::query()->where('activo', true)->firstOrFail();

        app(RegistrarPago::class)->ejecutar([
            'fecha_pago' => now()->toDateString(),
            'lineas' => [['alumno_id' => $alumnos[0]->id, 'cuota_id' => $cuotaActiva->id, 'monto' => 10000]],
            'monto_total' => 10000, 'liquidar_profesor' => '0',
        ], $admin);
        $anulado = Pago::query()->create(['fecha_pago' => now()->toDateString(), 'monto_total' => 3000]);
        $anulado->detalles()->create(['alumno_id' => $alumnos[1]->id, 'cuota_id' => $cuotaActiva->id, 'monto' => 3000]);
        $anulado->forceFill(['anulado_at' => now(), 'motivo_anulacion' => 'Error de carga'])->save();

        Gasto::query()->create(['sede_id' => $bloque->sede_id, 'fecha' => now()->toDateString(), 'tipo' => 'insumo', 'monto' => 3000, 'created_by' => $admin->id])->forceFill(['estado' => 'aprobado'])->save();
        Gasto::query()->create(['sede_id' => $bloque->sede_id, 'fecha' => now()->toDateString(), 'tipo' => 'reparacion', 'monto' => 5000, 'created_by' => $admin->id])->forceFill(['estado' => 'rechazado'])->save();

        $datos = app(TableroDireccionService::class)->datos($admin);
        $actual = $datos['actual'];

        $this->assertEquals(10000.0, $actual['cobrado'], 'Cobrado: solo el pago vigente', 0.01);
        $this->assertEquals(3000.0, $actual['gastos'], 'Gastos: sin el rechazado', 0.01);
        $this->assertEquals(7000.0, $actual['resultado'], '', 0.01);
        $this->assertEquals(70.0, $actual['margen'], '', 0.01);
        $this->assertEquals(30000.0, $actual['emitido'], 'Emitido: 3 activos × $10.000; la cuota desactivada no cuenta', 0.01);
        $this->assertEquals(33.3, $actual['cobranza'], '', 0.01);
        $this->assertSame(3, $datos['alumnos']['activos']);
    }
}
