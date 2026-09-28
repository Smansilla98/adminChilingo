<?php

namespace Tests\Feature\Api;

use App\Models\Cuota;
use App\Models\FacturacionMensual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

class FacturacionApiTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_carga_unica_por_periodo_y_edicion(): void
    {
        $sede = $this->sede('Palomar');
        $tesorero = $this->usuario('Tesorería');
        $this->asignarRol($tesorero->persona, 'tesorero');
        Sanctum::actingAs($tesorero->fresh());
        $datos = ['sede_id' => $sede->id, 'anio' => 2026, 'mes' => 8, 'cantidad_alumnos' => 40, 'monto_facturado' => 900000, 'monto_previsto' => 960000];

        $id = $this->postJson('/api/v1/facturacion', $datos)->assertCreated()->assertJsonPath('data.diferencia', -60000)->assertJsonPath('data.puede_editar', true)->json('data.id');
        $this->postJson('/api/v1/facturacion', $datos)->assertStatus(422)->assertJsonValidationErrors('mes');
        $this->postJson('/api/v1/facturacion', ['mes' => 13] + $datos)->assertStatus(422)->assertJsonValidationErrors('mes');

        $this->putJson("/api/v1/facturacion/{$id}", ['cantidad_alumnos' => 41, 'monto_facturado' => 960000])->assertOk()->assertJsonPath('data.cantidad_alumnos', 41);
        $r = $this->getJson('/api/v1/facturacion?anio=2026')->assertOk();
        $this->assertEquals(960000, $r->json('totales.facturado'));
    }

    public function test_alcance_de_sede_en_carga_y_edicion(): void
    {
        $palomar = $this->sede('Palomar');
        $varela = $this->sede('Varela');
        $ajena = FacturacionMensual::query()->create(['sede_id' => $varela->id, 'año' => 2026, 'mes' => 8, 'cantidad_alumnos' => 1, 'monto_facturado' => 1]);
        $tesorero = $this->usuario('Tesorería sede');
        $this->asignarRol($tesorero->persona, 'tesorero', 'sede', $palomar);
        Sanctum::actingAs($tesorero->fresh());

        $this->postJson('/api/v1/facturacion', ['sede_id' => $varela->id, 'anio' => 2026, 'mes' => 9, 'cantidad_alumnos' => 1, 'monto_facturado' => 1])->assertForbidden();
        $this->postJson('/api/v1/facturacion', ['anio' => 2026, 'mes' => 9, 'cantidad_alumnos' => 1, 'monto_facturado' => 1])->assertForbidden();
        $this->putJson("/api/v1/facturacion/{$ajena->id}", ['cantidad_alumnos' => 99, 'monto_facturado' => 99])->assertForbidden();
        $this->getJson("/api/v1/facturacion/{$ajena->id}")->assertForbidden();
        $this->assertSame(0, $this->getJson('/api/v1/facturacion')->json('meta.total'));
        $this->getJson('/api/v1/facturacion/cierre-mes')->assertForbidden();

        // La web aplica la misma regla.
        $this->actingAs($tesorero->fresh())->put(route('facturacion-mensual.update', $ajena), ['cantidad_alumnos' => 99, 'monto_facturado' => 99])->assertForbidden();
        $this->assertSame(1, (int) $ajena->fresh()->cantidad_alumnos);
    }

    public function test_cierre_de_mes_con_checklist(): void
    {
        $sede = $this->sede('Palomar');
        $this->bloque($sede);
        Cuota::query()->create(['nombre' => 'Agosto', 'año' => 2026, 'mes' => 8, 'monto' => 1, 'alcance' => 'general', 'activo' => true]);
        $contador = $this->usuario('Contador');
        $this->asignarRol($contador->persona, 'contador');
        Sanctum::actingAs($contador->fresh());

        $r = $this->getJson('/api/v1/facturacion/cierre-mes?mes=8&anio=2026')->assertOk();
        $items = collect($r->json('items'))->keyBy('clave');
        $this->assertFalse($items['asistencias']['ok']);
        $this->assertTrue($items['cuotas']['ok']);
        $this->assertTrue($items['comprobantes']['ok']);
        $this->assertSame(3, $r->json('total'));
        $this->postJson('/api/v1/facturacion', ['anio' => 2026, 'mes' => 8, 'cantidad_alumnos' => 1, 'monto_facturado' => 1])->assertForbidden();
    }
}
