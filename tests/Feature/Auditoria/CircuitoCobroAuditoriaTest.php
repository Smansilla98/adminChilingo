<?php

namespace Tests\Feature\Auditoria;

use App\Domain\Finanzas\EstadoCuentaService;
use App\Domain\Finanzas\MorosidadService;
use App\Domain\Finanzas\PagoService;
use App\Domain\Finanzas\RegistrarPago;
use App\Models\Alumno;
use App\Models\Cuota;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\Escenarios;
use Tests\TestCase;

/**
 * Auditoría: circuito cuota → morosidad → pago parcial → sobrepago rechazado → pago total → anulación.
 * Todos los importes esperados se calculan a mano en cada prueba (sin reglas de cobro activas).
 */
class CircuitoCobroAuditoriaTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private Alumno $ana;

    private Cuota $cuota;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->admin('Tesorería');
        $bloque = $this->bloque($this->sede('Palomar'), 'Martes 19');
        $this->ana = $this->inscribirAlumno($this->persona('Ana Auditoría'), $bloque);
        // Cuota de $10.000 que venció hace 40 días: morosa en el tramo 31-60.
        $this->cuota = Cuota::query()->create([
            'nombre' => 'Mensual octubre', 'año' => (int) now()->year, 'mes' => 10, 'monto' => 10000,
            'alcance' => 'bloque', 'bloque_id' => $bloque->id, 'activo' => true,
            'fecha_vencimiento' => now()->subDays(40)->toDateString(),
        ]);
    }

    /** @param  list<array{alumno_id: int, cuota_id: int, monto: float}>  $lineas */
    private function cobrar(array $lineas, float $total, ?string $fecha = null): Pago
    {
        $r = app(RegistrarPago::class)->ejecutar([
            'fecha_pago' => $fecha ?? now()->toDateString(),
            'lineas' => $lineas,
            'monto_total' => $total,
            'liquidar_profesor' => '0',
        ], $this->admin);

        return $r['pago'];
    }

    private function saldoAna(): float
    {
        return (float) app(EstadoCuentaService::class)->paraAlumno($this->ana, (int) now()->year)['totales']['saldo'];
    }

    private function morosoAna(): ?array
    {
        return collect(app(MorosidadService::class)->deudores([], $this->admin))->firstWhere('alumno_id', $this->ana->id);
    }

    public function test_circuito_completo_de_cobro_y_morosidad_con_cifras_exactas(): void
    {
        $lin = fn (float $m) => [['alumno_id' => $this->ana->id, 'cuota_id' => $this->cuota->id, 'monto' => $m]];

        // 1) Recién generada y vencida: deuda total 10.000, 40 días de mora, tramo 31-60.
        $this->assertSame(10000.0, $this->saldoAna());
        $this->assertSame(10000.0, $this->morosoAna()['saldo_vencido']);
        $this->assertSame(40, $this->morosoAna()['dias_mora']);
        $this->assertSame('31-60', $this->morosoAna()['tramo']);

        // 2) Pago parcial de 4.000: saldo 6.000, estado parcial, morosidad por 6.000.
        $p1 = $this->cobrar($lin(4000), 4000);
        $this->assertSame(6000.0, $this->saldoAna());
        $this->assertSame(6000.0, $this->morosoAna()['saldo_vencido']);
        $estado = app(EstadoCuentaService::class)->paraAlumno($this->ana, (int) now()->year)['items'][0];
        $this->assertSame('parcial', $estado['estado']);
        $this->assertSame(4000.0, $estado['pagado']);

        // 3) Sobrepago: 6.500 sobre un saldo de 6.000 se rechaza y no deja rastro.
        try {
            $this->cobrar($lin(6500), 6500);
            $this->fail('Debía rechazar un pago que supera el saldo');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('supera el saldo', $e->validator->errors()->first('lineas.0.monto'));
        }
        $this->assertSame(1, Pago::query()->count());
        $this->assertSame(6000.0, $this->saldoAna());

        // 4) Pago total del saldo: 6.000 cancela la cuota y sale de morosos.
        $p2 = $this->cobrar($lin(6000), 6000);
        $this->assertSame(0.0, $this->saldoAna());
        $this->assertNull($this->morosoAna());
        $this->assertSame('pagada', app(EstadoCuentaService::class)->paraAlumno($this->ana, (int) now()->year)['items'][0]['estado']);

        // 5) Anular el primer pago (4.000): la deuda vuelve a 4.000 y el alumno vuelve a morosos.
        app(PagoService::class)->anular($p1, $this->admin, 'Recibo cargado por error');
        $this->assertSame(4000.0, $this->saldoAna());
        $this->assertSame(4000.0, $this->morosoAna()['saldo_vencido']);

        // 6) Cuadre: lo vigente en pagos y en líneas de pago coincide (solo queda el pago de 6.000).
        $vigente = (float) Pago::query()->vigentes()->sum('monto_total');
        $lineas = (float) PagoDetalle::query()->sum('monto');
        $this->assertSame(6000.0, $vigente);
        $this->assertSame(6000.0, $lineas);
        // Crudo (con anulados): 4.000 + 6.000 = 10.000 en líneas; vigente solo 6.000.
        $this->assertSame(10000.0, (float) PagoDetalle::withoutGlobalScopes()->sum('monto'));
        $this->assertSame(6000.0, (float) $p2->monto_total);
    }

    public function test_monto_total_debe_coincidir_con_la_suma_de_lineas_con_mensaje_humano(): void
    {
        $otra = $this->inscribirAlumno($this->persona('Beto Auditoría'), $this->bloque($this->sede('Varela'), 'Jueves'));
        $cuotaOtra = Cuota::query()->create([
            'nombre' => 'Mensual', 'año' => (int) now()->year, 'mes' => 10, 'monto' => 8000,
            'alcance' => 'bloque', 'bloque_id' => $otra->bloques->first()->id, 'activo' => true,
            'fecha_vencimiento' => now()->subDays(5)->toDateString(),
        ]);

        try {
            $this->cobrar([
                ['alumno_id' => $this->ana->id, 'cuota_id' => $this->cuota->id, 'monto' => 3000],
                ['alumno_id' => $otra->id, 'cuota_id' => $cuotaOtra->id, 'monto' => 2000],
            ], 6000);
            $this->fail('Debía rechazar total distinto a la suma de líneas');
        } catch (ValidationException $e) {
            $msg = $e->validator->errors()->first('monto_total');
            $this->assertStringContainsString('$6.000,00', $msg);
            $this->assertStringContainsString('$5.000,00', $msg);
        }
        $this->assertSame(0, Pago::query()->count());
    }

    public function test_recibo_pdf_y_detalle_del_pago_responden(): void
    {
        $p = $this->cobrar([['alumno_id' => $this->ana->id, 'cuota_id' => $this->cuota->id, 'monto' => 2500]], 2500);
        $this->actingAs($this->admin)->get(route('pagos.show', $p))->assertOk()->assertSee('2.500');
        $this->actingAs($this->admin)->get(route('pagos.recibo', $p))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($this->admin)->get(route('pagos.index'))->assertOk();
    }
}
