<?php

namespace Tests\Feature;

use App\Domain\Academico\CuotasDelCicloService;
use App\Domain\Personas\CicloLectivoService;
use App\Models\CicloLectivo;
use App\Models\Cuota;
use App\Models\Pago;
use App\Models\PagoDetalle;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

class CuotasMontoCicloTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private CicloLectivo $ciclo;

    private function preparar(): void
    {
        $admin = $this->admin();
        $this->ciclo = app(CicloLectivoService::class)->abrir(['anio' => 2027], $admin);
        $this->bloque($this->sede('Palomar'), 'Martes');
        $this->bloque($this->sede('Varela'), 'Jueves');
        app(CuotasDelCicloService::class)->generar($this->ciclo, 20000, [], $admin);
    }

    public function test_la_vista_previa_no_escribe_y_cuenta_pagos(): void
    {
        $this->preparar();
        $cuota = Cuota::query()->where('año', 2027)->where('mes', 3)->firstOrFail();
        $ana = $this->inscribirAlumno($this->persona('Ana'), $cuota->bloque);
        $pago = Pago::query()->create(['fecha_pago' => now(), 'monto_total' => 20000]);
        $pago->detalles()->create(['alumno_id' => $ana->id, 'cuota_id' => $cuota->id, 'monto' => 20000]);

        $previa = app(CuotasDelCicloService::class)->vistaPreviaMonto($this->ciclo, 25000, []);

        $this->assertSame(24, $previa['total']);
        $this->assertSame(24, $previa['cambian']);
        $this->assertSame(1, $previa['con_pagos']);
        $this->assertSame(20000.0, (float) Cuota::query()->whereKey($cuota->id)->value('monto'), 'la vista previa no cambia nada');
    }

    public function test_aplicar_cambia_el_monto_por_bloque_y_no_toca_los_pagos(): void
    {
        $this->preparar();
        $admin = $this->admin();
        $martes = \App\Models\Bloque::query()->where('nombre', 'Martes')->firstOrFail();
        $cuota = Cuota::query()->where('bloque_id', $martes->id)->where('año', 2027)->where('mes', 3)->firstOrFail();
        $ana = $this->inscribirAlumno($this->persona('Ana'), $martes);
        $pago = Pago::query()->create(['fecha_pago' => now(), 'monto_total' => 20000]);
        $detalle = $pago->detalles()->create(['alumno_id' => $ana->id, 'cuota_id' => $cuota->id, 'monto' => 20000]);

        $r = app(CuotasDelCicloService::class)->actualizarMonto($this->ciclo, 25000, [$martes->id => 30000], $admin);

        $this->assertSame(24, $r['actualizadas']);
        $this->assertSame(30000.0, (float) Cuota::query()->whereKey($cuota->id)->value('monto'), 'Martes usa su monto');
        $this->assertSame(25000.0, (float) Cuota::query()->where('bloque_id', \App\Models\Bloque::query()->where('nombre', 'Jueves')->value('id'))->where('mes', 3)->value('monto'));
        $this->assertSame(20000.0, (float) $detalle->fresh()->monto, 'el pago registrado no cambia');
        $this->assertSame(1, PagoDetalle::query()->count());
        $this->assertDatabaseHas('auditoria', ['entidad_tipo' => 'Cuota', 'entidad_id' => $cuota->id, 'accion' => 'updated']);
        $this->assertDatabaseHas('auditoria', ['entidad_tipo' => 'CicloLectivo', 'entidad_id' => $this->ciclo->id, 'accion' => 'ciclo.cuotas_monto']);

        // Segunda vez: nada que cambiar.
        $this->assertSame(0, app(CuotasDelCicloService::class)->actualizarMonto($this->ciclo, 25000, [$martes->id => 30000], $admin)['actualizadas']);
    }

    public function test_sin_permiso_de_cuotas_no_cambia_nada(): void
    {
        $this->preparar();
        $contador = $this->usuario('Contadora');
        $this->asignarRol($contador->persona, 'contador');
        $this->expectException(AuthorizationException::class);
        app(CuotasDelCicloService::class)->actualizarMonto($this->ciclo, 1, [], $contador->fresh());
    }

    public function test_web_previa_y_aplicar(): void
    {
        $this->preparar();
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('ciclos.montos', $this->ciclo), ['accion' => 'previa', 'monto' => 26000])
            ->assertSessionHas('previa_montos')->assertRedirect();
        $this->assertSame(20000.0, (float) Cuota::query()->where('año', 2027)->value('monto'));

        $this->actingAs($admin)->post(route('ciclos.montos', $this->ciclo), ['accion' => 'aplicar', 'monto' => 26000])->assertSessionHas('success');
        $this->assertSame(26000.0, (float) Cuota::query()->where('año', 2027)->value('monto'));
    }

    public function test_api_previa_y_aplicar_y_sin_permiso(): void
    {
        $this->preparar();
        Sanctum::actingAs($this->admin());
        $this->postJson("/api/v1/ciclos-lectivos/{$this->ciclo->id}/montos", ['accion' => 'previa', 'monto' => 26000])
            ->assertOk()->assertJsonPath('data.total', 24);
        $this->postJson("/api/v1/ciclos-lectivos/{$this->ciclo->id}/montos", ['accion' => 'aplicar', 'monto' => 26000])
            ->assertOk()->assertJsonPath('data.actualizadas', 24);

        $contador = $this->usuario('Contadora');
        $this->asignarRol($contador->persona, 'contador');
        Sanctum::actingAs($contador->fresh());
        $this->postJson("/api/v1/ciclos-lectivos/{$this->ciclo->id}/montos", ['accion' => 'aplicar', 'monto' => 1])->assertForbidden();
    }
}
