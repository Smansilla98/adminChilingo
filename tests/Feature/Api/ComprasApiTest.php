<?php

namespace Tests\Feature\Api;

use App\Models\InventarioItem;
use App\Models\OrdenCompra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

class ComprasApiTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function datos(int $sedeId, array $extra = []): array
    {
        return $extra + [
            'sede_id' => $sedeId, 'motivo' => 'reposicion', 'estado' => 'borrador', 'justificacion' => 'Parches rotos',
            'items' => [
                ['descripcion' => 'Parche 22"', 'cantidad' => 4, 'precio_estimado' => 15000],
                ['descripcion' => 'Baqueta', 'cantidad' => 10, 'precio_estimado' => 2000, 'unidad' => 'par'],
            ],
        ];
    }

    public function test_encargado_crea_orden_con_totales_pero_no_la_aprueba(): void
    {
        $palomar = $this->sede('Palomar');
        $varela = $this->sede('Varela');
        $encargado = $this->usuario('Encargado');
        $this->asignarRol($encargado->persona, 'encargado', 'sede', $palomar);
        Sanctum::actingAs($encargado->fresh());

        $this->postJson('/api/v1/compras', $this->datos($varela->id))->assertForbidden();
        $this->postJson('/api/v1/compras', $this->datos($palomar->id, ['items' => []]))->assertStatus(422)->assertJsonValidationErrors('items');
        $this->postJson('/api/v1/compras', $this->datos($palomar->id, ['estado' => 'aprobada']))->assertForbidden();

        $r = $this->postJson('/api/v1/compras', $this->datos($palomar->id))->assertCreated();
        $id = $r->json('data.id');
        $this->assertEquals(80000, $r->json('data.total_estimado'));
        $this->assertSame('par', $r->json('data.items.1.unidad'));
        $this->assertNotContains('aprobada', $r->json('data.acciones.estados_posibles'));

        $this->postJson("/api/v1/compras/{$id}/estado", ['estado' => 'enviada'])->assertOk()->assertJsonPath('data.estado', 'enviada');
        $this->postJson("/api/v1/compras/{$id}/estado", ['estado' => 'aprobada'])->assertForbidden();

        $tesorero = $this->usuario('Tesorería');
        $this->asignarRol($tesorero->persona, 'tesorero');
        $this->asignarPermiso($tesorero->persona, 'compras.create');
        Sanctum::actingAs($tesorero->fresh());
        $this->postJson("/api/v1/compras/{$id}/estado", ['estado' => 'aprobada'])->assertOk()->assertJsonPath('data.estado', 'aprobada');
        $this->assertSame($encargado->id, OrdenCompra::query()->find($id)->created_by, 'el creador se conserva');
    }

    public function test_alcance_en_listado_detalle_edicion_y_baja(): void
    {
        $palomar = $this->sede('Palomar');
        $varela = $this->sede('Varela');
        $ajena = OrdenCompra::query()->create(['sede_id' => $varela->id, 'motivo' => 'otro', 'estado' => 'borrador', 'total_estimado' => 0]);
        $encargado = $this->usuario('Encargado');
        $this->asignarRol($encargado->persona, 'encargado', 'sede', $palomar);
        Sanctum::actingAs($encargado->fresh());

        $this->assertSame(0, $this->getJson('/api/v1/compras')->assertOk()->json('meta.total'));
        $this->getJson("/api/v1/compras/{$ajena->id}")->assertForbidden();
        $this->putJson("/api/v1/compras/{$ajena->id}", $this->datos($palomar->id))->assertForbidden();
        $this->deleteJson("/api/v1/compras/{$ajena->id}")->assertForbidden();

        $id = $this->postJson('/api/v1/compras', $this->datos($palomar->id))->json('data.id');
        $this->putJson("/api/v1/compras/{$id}", $this->datos($palomar->id, ['items' => [['descripcion' => 'Solo uno', 'cantidad' => 1, 'precio_estimado' => 500]]]))
            ->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.total_estimado', 500);
        $this->deleteJson("/api/v1/compras/{$id}")->assertOk();
        $this->assertNull(OrdenCompra::query()->find($id));
    }

    public function test_plan_de_compras_por_sede_del_alcance(): void
    {
        $palomar = $this->sede('Palomar');
        $varela = $this->sede('Varela');
        $bloque = $this->bloque($palomar);
        $bloque->horarios()->create(['dia_semana' => 1, 'hora_inicio' => '18:00:00', 'hora_fin' => '19:00:00']);
        foreach (range(1, 5) as $i) {
            $this->inscribirAlumno($this->persona("A{$i}"), $bloque);
        }
        InventarioItem::query()->create(['sede_id' => $palomar->id, 'tipo' => 'instrumento', 'nombre' => 'Surdo', 'cantidad' => 1, 'propietario_tipo' => 'escuela', 'estado' => 'bueno']);
        $encargado = $this->usuario('Encargado');
        $this->asignarRol($encargado->persona, 'encargado', 'sede', $palomar);
        Sanctum::actingAs($encargado->fresh());

        $r = $this->getJson('/api/v1/compras/plan')->assertOk();
        $this->assertSame([$palomar->id], collect($r->json('sedes'))->pluck('sede.id')->all());
        $this->assertSame(3, $r->json('sedes.0.tambores_necesarios'));
        $this->assertSame(2, $r->json('sedes.0.tambores_faltantes'));
        $this->assertNotContains($varela->id, collect($r->json('sedes'))->pluck('sede.id')->all());
    }
}
