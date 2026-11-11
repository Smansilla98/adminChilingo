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

    public function test_gasto_va_a_la_papelera_con_motivo_y_se_restaura(): void
    {
        $admin = $this->admin();
        $gasto = \App\Models\Gasto::query()->create(['fecha' => now()->toDateString(), 'tipo' => 'insumo', 'monto' => 5000, 'descripcion' => 'Parches']);
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/gastos/{$gasto->id}", ['motivo' => 'Cargado dos veces'])->assertOk();
        $this->assertSoftDeleted('gastos', ['id' => $gasto->id, 'eliminado_por' => $admin->id, 'motivo_eliminacion' => 'Cargado dos veces']);
        $this->assertSame(0, (int) $this->getJson('/api/v1/gastos')->json('meta.total'));
        $this->assertSame(0.0, (float) $this->getJson('/api/v1/gastos')->json('total_monto'));
        $this->getJson('/api/v1/gastos?papelera=1')->assertOk()->assertJsonPath('data.0.motivo_eliminacion', 'Cargado dos veces');

        $this->postJson("/api/v1/gastos/{$gasto->id}/restaurar")->assertOk()->assertJsonPath('data.eliminado', false);
        $this->assertNotSoftDeleted('gastos', ['id' => $gasto->id]);

        // Web: papelera y restaurar.
        $this->actingAs($admin)->delete(route('gastos.destroy', $gasto), ['motivo' => 'Error'])->assertRedirect(route('gastos.index'));
        $this->actingAs($admin)->get(route('gastos.index', ['papelera' => 1]))->assertOk()->assertSee('Restaurar');
        $this->actingAs($admin)->post(route('gastos.restaurar', $gasto->id))->assertRedirect(route('gastos.index'));
        $this->assertNotSoftDeleted('gastos', ['id' => $gasto->id]);
    }

    public function test_gasto_de_la_gira_va_a_la_papelera(): void
    {
        $admin = $this->admin();
        $gasto = \App\Models\VillaGesellGasto::query()->create(['tipo' => 'nafta', 'concepto' => 'Ida', 'monto' => 1000, 'modo' => 'total']);

        $this->actingAs($admin)->delete(route('villa-gesell.gastos.destroy', $gasto), ['motivo' => 'Duplicado'])->assertRedirect();
        $this->assertSoftDeleted('villa_gesell_gastos', ['id' => $gasto->id, 'motivo_eliminacion' => 'Duplicado']);
        $this->assertDatabaseHas('auditoria', ['entidad_tipo' => 'VillaGesellGasto', 'entidad_id' => $gasto->id, 'accion' => 'deleted']);
        $this->actingAs($admin)->get(route('villa-gesell.gastos.index', ['papelera' => 1]))->assertOk()->assertSee('Duplicado');

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/villa-gesell/gastos/papelera')->assertOk()->assertJsonPath('data.0.id', $gasto->id);
        $this->postJson("/api/v1/villa-gesell/gastos/{$gasto->id}/restaurar")->assertOk();
        $this->assertNotSoftDeleted('villa_gesell_gastos', ['id' => $gasto->id]);
    }

    public function test_borrar_un_alumno_o_una_cuota_con_pagos_no_borra_la_plata(): void
    {
        $bloque = $this->bloque($this->sede('Palomar'));
        $ana = $this->inscribirAlumno($this->persona('Ana'), $bloque);
        $cuota = $this->cuota();
        $pago = Pago::query()->create(['fecha_pago' => now(), 'monto_total' => 24000]);
        $pago->detalles()->create(['alumno_id' => $ana->id, 'cuota_id' => $cuota->id, 'monto' => 24000]);
        $comprobante = \App\Models\ComprobanteCuotaAlumno::query()->create([
            'alumno_id' => $ana->id, 'fecha_pago' => now()->toDateString(), 'monto_total' => 24000, 'estado' => 'pendiente',
        ]);
        \App\Models\ComprobanteCuotaAlumnoItem::query()->create([
            'comprobante_cuota_alumno_id' => $comprobante->id, 'cuota_id' => $cuota->id, 'bloque_id' => $bloque->id, 'monto' => 24000,
        ]);

        // Por la aplicación: mensaje claro.
        $this->actingAs($this->admin())->delete(route('alumnos.destroy', $ana))->assertSessionHasErrors('eliminar');

        // Aunque alguien se saltee la aplicación, la base no arrastra los pagos.
        foreach ([fn () => $ana->delete(), fn () => $cuota->delete(), fn () => $bloque->delete()] as $borrar) {
            try {
                \Illuminate\Support\Facades\DB::transaction($borrar);
                $this->fail('La base debió rechazar el borrado.');
            } catch (\Illuminate\Database\QueryException) {
                // esperado
            }
        }
        $this->assertDatabaseHas('pago_detalles', ['alumno_id' => $ana->id, 'cuota_id' => $cuota->id, 'monto' => 24000]);
        $this->assertDatabaseHas('comprobante_cuota_alumno_items', ['cuota_id' => $cuota->id]);
        $this->assertDatabaseHas('alumnos', ['id' => $ana->id]);
    }
}
