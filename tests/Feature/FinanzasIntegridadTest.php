<?php

namespace Tests\Feature;

use App\Domain\Finanzas\EstadoCuentaService;
use App\Models\Beca;
use App\Models\Cuota;
use App\Models\Pago;
use App\Models\PagoDetalle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenarios;
use Tests\TestCase;

class FinanzasIntegridadTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_no_se_borra_una_sede_con_alumnos_ni_su_historial(): void
    {
        $admin = $this->admin();
        $sede = $this->sede('Banfield');
        $alumno = $this->inscribirAlumno($this->persona('Ana'), $this->bloque($sede));

        $this->actingAs($admin)->delete(route('sedes.destroy', $sede))->assertSessionHasErrors('eliminar');

        $this->assertDatabaseHas('sedes', ['id' => $sede->id]);
        $this->assertDatabaseHas('alumnos', ['id' => $alumno->id]);
    }

    public function test_no_se_borra_una_cuota_con_pagos(): void
    {
        $admin = $this->admin();
        $alumno = $this->inscribirAlumno($this->persona('Ana'), $this->bloque($this->sede('Palomar')));
        $cuota = Cuota::query()->create(['nombre' => 'Abril', 'año' => 2026, 'mes' => 4, 'monto' => 1000, 'alcance' => 'general', 'activo' => true]);
        $pago = Pago::query()->create(['fecha_pago' => now(), 'monto_total' => 1000]);
        $pago->detalles()->create(['alumno_id' => $alumno->id, 'cuota_id' => $cuota->id, 'monto' => 1000]);

        $this->actingAs($admin)->delete(route('cuotas.destroy', $cuota))->assertSessionHasErrors('eliminar');
        $this->assertDatabaseHas('pago_detalles', ['cuota_id' => $cuota->id]);
    }

    public function test_anular_pago_lo_excluye_de_saldos_y_queda_auditado(): void
    {
        $tesorero = $this->usuario('Tesorera');
        $this->asignarRol($tesorero->persona, 'tesorero');
        $alumno = $this->inscribirAlumno($this->persona('Ana'), $this->bloque($this->sede('Quilmes')));
        $cuota = Cuota::query()->create(['nombre' => 'Mayo', 'año' => (int) now()->year, 'mes' => 5, 'monto' => 1000, 'alcance' => 'general', 'activo' => true]);
        $pago = Pago::query()->create(['fecha_pago' => now(), 'monto_total' => 1000]);
        $pago->detalles()->create(['alumno_id' => $alumno->id, 'cuota_id' => $cuota->id, 'monto' => 1000]);

        $this->actingAs($tesorero->fresh())->post(route('pagos.anular', $pago), ['motivo' => 'Transferencia rechazada'])
            ->assertRedirect(route('pagos.show', $pago));

        $this->assertNotNull($pago->fresh()->anulado_at);
        $this->assertSame(0, PagoDetalle::query()->where('alumno_id', $alumno->id)->count());
        $this->assertSame(1, $pago->fresh()->detalles()->count()); // el historial sigue visible desde el pago
        $cuenta = app(EstadoCuentaService::class)->paraAlumno($alumno);
        $this->assertSame(1000.0, $cuenta['totales']['saldo']);
        $this->assertDatabaseHas('auditoria', ['entidad_tipo' => 'Pago', 'entidad_id' => $pago->id, 'accion' => 'anulado']);

        // No se anula dos veces.
        $this->actingAs($tesorero->fresh())->post(route('pagos.anular', $pago), ['motivo' => 'Otra vez'])->assertForbidden();
    }

    public function test_estado_de_cuenta_aplica_beca_y_alcance_de_cuota(): void
    {
        $palomar = $this->sede('Palomar');
        $quilmes = $this->sede('Quilmes');
        $bloque = $this->bloque($palomar);
        $alumno = $this->inscribirAlumno($this->persona('Beto'), $bloque);
        $anio = (int) now()->year;

        Cuota::query()->create(['nombre' => 'General enero', 'año' => $anio, 'mes' => 1, 'monto' => 1000, 'alcance' => 'general', 'activo' => true]);
        Cuota::query()->create(['nombre' => 'Palomar febrero', 'año' => $anio, 'mes' => 2, 'monto' => 2000, 'alcance' => 'sede', 'sede_id' => $palomar->id, 'activo' => true]);
        Cuota::query()->create(['nombre' => 'Quilmes febrero', 'año' => $anio, 'mes' => 2, 'monto' => 9999, 'alcance' => 'sede', 'sede_id' => $quilmes->id, 'activo' => true]);
        $febrero = Cuota::query()->where('nombre', 'Palomar febrero')->first();
        Beca::query()->create(['alumno_id' => $alumno->id, 'tipo' => 'porcentaje', 'porcentaje' => 50, 'fecha_inicio' => "$anio-02-01", 'estado' => 'activa']);
        $pago = Pago::query()->create(['fecha_pago' => now(), 'monto_total' => 1000]);
        $pago->detalles()->create(['alumno_id' => $alumno->id, 'cuota_id' => $febrero->id, 'monto' => 1000]);

        $cuenta = app(EstadoCuentaService::class)->paraAlumno($alumno, $anio);

        $this->assertCount(2, $cuenta['items']); // la de Quilmes no le corresponde
        [$enero, $feb] = $cuenta['items'];
        $this->assertSame(1000.0, $enero['neto']);        // beca empieza en febrero
        $this->assertSame('pendiente', $enero['estado']);
        $this->assertSame(1000.0, $feb['neto']);          // 2000 - 50%
        $this->assertSame('pagada', $feb['estado']);
        $this->assertSame(1000.0, $cuenta['totales']['saldo']);
    }

    public function test_becado_es_un_rol_derivado(): void
    {
        $user = $this->usuario('Becada');
        $alumno = $this->inscribirAlumno($user->persona, $this->bloque($this->sede('Varela')));
        $this->assertFalse($user->fresh()->acceso()->tieneRol('becado'));

        Beca::query()->create(['alumno_id' => $alumno->id, 'tipo' => 'total', 'fecha_inicio' => now()->subMonth(), 'estado' => 'activa']);

        $this->assertTrue($user->fresh()->acceso()->tieneRol('becado'));
    }

    public function test_gasto_de_quien_no_aprueba_queda_pendiente(): void
    {
        $sede = $this->sede('Varela');
        $resp = $this->usuario('Responsable');
        $this->asignarRol($resp->persona, 'responsable_de_sede', 'sede', $sede);

        $this->actingAs($resp->fresh())->post(route('gastos.store'), [
            'sede_id' => $sede->id, 'fecha' => now()->toDateString(), 'tipo' => 'reparacion', 'monto' => 5000,
        ])->assertRedirect();

        $this->assertDatabaseHas('gastos', ['sede_id' => $sede->id, 'estado' => 'pendiente']);
    }
}
