<?php

namespace Tests\Feature\Api;

use App\Models\InventarioItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

class InventarioBajaApiTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_baja_de_item_requiere_permiso_y_alcance(): void
    {
        $palomar = $this->sede('Palomar');
        $item = InventarioItem::query()->create(['sede_id' => $palomar->id, 'tipo' => 'instrumento', 'nombre' => 'Surdo', 'cantidad' => 1, 'propietario_tipo' => 'escuela', 'estado' => 'bueno']);
        $encargado = $this->usuario('Encargado');
        $this->asignarRol($encargado->persona, 'encargado', 'sede', $palomar);
        Sanctum::actingAs($encargado->fresh());
        $this->deleteJson("/api/v1/inventario/{$item->id}")->assertForbidden();

        $resp = $this->usuario('Responsable');
        $this->asignarRol($resp->persona, 'responsable_de_inventario', 'sede', $this->sede('Varela'));
        Sanctum::actingAs($resp->fresh());
        $this->deleteJson("/api/v1/inventario/{$item->id}")->assertForbidden();

        Sanctum::actingAs($this->admin());
        $this->getJson("/api/v1/inventario/{$item->id}")->assertJsonPath('data.puede_eliminar', true);
        $this->deleteJson("/api/v1/inventario/{$item->id}")->assertOk();
        $this->assertNull(InventarioItem::query()->find($item->id));
    }
}
