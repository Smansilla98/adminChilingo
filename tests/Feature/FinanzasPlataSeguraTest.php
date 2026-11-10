<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Cuota;
use App\Models\Pago;
use App\Models\PagoDetalle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

/**
 * Integridad de la plata: ediciones auditadas, FKs que no borran pagos, papelera,
 * registro único web/API con idempotencia y control de doble cobro.
 */
class FinanzasPlataSeguraTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function tesorero()
    {
        $u = $this->usuario('Tesorería');
        $this->asignarRol($u->persona, 'tesorero');

        return $u->fresh();
    }

    private function cuota(array $datos = []): Cuota
    {
        return Cuota::query()->create($datos + ['nombre' => 'Marzo', 'año' => (int) now()->year, 'mes' => 3, 'monto' => 24000, 'alcance' => 'general', 'activo' => true]);
    }

    public function test_editar_un_pago_deja_auditoria_completa_antes_y_despues(): void
    {
        $bloque = $this->bloque($this->sede('Palomar'));
        $ana = $this->inscribirAlumno($this->persona('Ana'), $bloque);
        $beto = $this->inscribirAlumno($this->persona('Beto'), $bloque);
        $cuota = $this->cuota();
        Sanctum::actingAs($this->tesorero());

        $id = $this->postJson('/api/v1/pagos', [
            'fecha_pago' => now()->toDateString(),
            'monto_total' => 24000,
            'lineas' => [['alumno_id' => $ana->id, 'cuota_id' => $cuota->id, 'monto' => 24000]],
        ])->assertCreated()->json('data.id');
        $detalleViejo = PagoDetalle::query()->where('pago_id', $id)->value('id');

        $this->putJson("/api/v1/pagos/{$id}", [
            'fecha_pago' => now()->toDateString(),
            'monto_total' => 24000,
            'lineas' => [['alumno_id' => $beto->id, 'cuota_id' => $cuota->id, 'monto' => 24000]],
        ])->assertOk();

        $registro = Auditoria::query()->where('entidad_tipo', 'Pago')->where('entidad_id', $id)->where('accion', 'editado')->firstOrFail();
        $this->assertSame($ana->id, $registro->datos_anteriores['lineas'][0]['alumno_id']);
        $this->assertSame($beto->id, $registro->datos_nuevos['lineas'][0]['alumno_id']);
        // La línea quitada quedó registrada una por una (no borrado en masa).
        $this->assertDatabaseHas('auditoria', ['entidad_tipo' => 'PagoDetalle', 'entidad_id' => $detalleViejo, 'accion' => 'deleted']);
    }

    public function test_un_pago_anulado_no_se_edita(): void
    {
        $ana = $this->inscribirAlumno($this->persona('Ana'), $this->bloque($this->sede('Palomar')));
        $cuota = $this->cuota();
        $pago = Pago::query()->create(['fecha_pago' => now(), 'monto_total' => 24000]);
        $pago->detalles()->create(['alumno_id' => $ana->id, 'cuota_id' => $cuota->id, 'monto' => 24000]);
        $pago->forceFill(['anulado_at' => now(), 'motivo_anulacion' => 'error de carga'])->save();
        Sanctum::actingAs($this->admin());

        $r = $this->putJson("/api/v1/pagos/{$pago->id}", [
            'fecha_pago' => now()->toDateString(),
            'monto_total' => 100,
            'lineas' => [['alumno_id' => $ana->id, 'cuota_id' => $cuota->id, 'monto' => 100]],
        ]);
        $this->assertContains($r->status(), [403, 422]);
        $this->assertSame('24000.00', (string) $pago->fresh()->monto_total);
    }
}
