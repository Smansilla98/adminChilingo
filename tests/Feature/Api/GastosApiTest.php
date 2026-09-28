<?php

namespace Tests\Feature\Api;

use App\Models\Gasto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

class GastosApiTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_responsable_de_sede_registra_pendiente_y_tesoreria_aprueba(): void
    {
        $palomar = $this->sede('Palomar');
        $varela = $this->sede('Varela');
        $responsable = $this->usuario('Responsable');
        $this->asignarRol($responsable->persona, 'responsable_de_sede', 'sede', $palomar);
        Sanctum::actingAs($responsable->fresh());
        $base = ['fecha' => now()->toDateString(), 'tipo' => 'servicio', 'subtipo' => 'luz', 'monto' => 15000, 'descripcion' => 'Factura de luz'];

        $this->postJson('/api/v1/gastos', $base)->assertForbidden(); // sin sede = toda la escuela
        $this->postJson('/api/v1/gastos', $base + ['sede_id' => $varela->id])->assertForbidden();
        $this->postJson('/api/v1/gastos', ['sede_id' => $palomar->id, 'tipo' => 'cualquiera'] + $base)->assertStatus(422)->assertJsonValidationErrors('tipo');

        $id = $this->postJson('/api/v1/gastos', $base + ['sede_id' => $palomar->id])->assertCreated()
            ->assertJsonPath('data.estado', 'pendiente')->assertJsonPath('data.subtipo_nombre', 'Luz')->assertJsonPath('data.acciones.aprobar', false)->json('data.id');
        $this->postJson("/api/v1/gastos/{$id}/decision", ['decision' => 'aprobado'])->assertForbidden();

        $tesorero = $this->usuario('Tesorería');
        $this->asignarRol($tesorero->persona, 'tesorero');
        Sanctum::actingAs($tesorero->fresh());
        $this->getJson("/api/v1/gastos/{$id}")->assertOk()->assertJsonPath('data.acciones.aprobar', true);
        $this->postJson("/api/v1/gastos/{$id}/decision", ['decision' => 'talvez'])->assertStatus(422);
        $this->postJson("/api/v1/gastos/{$id}/decision", ['decision' => 'aprobado'])->assertOk()
            ->assertJsonPath('data.estado', 'aprobado')->assertJsonPath('data.aprobado_por', 'Tesorería');

        // Quien puede aprobar registra directamente aprobado.
        $this->postJson('/api/v1/gastos', $base)->assertCreated()->assertJsonPath('data.estado', 'aprobado');
        $r = $this->getJson('/api/v1/gastos?estado=aprobado')->assertOk();
        $this->assertSame(2, $r->json('meta.total'));
        $this->assertEquals(30000, $r->json('total_monto'));
    }

    public function test_listado_y_edicion_respetan_la_sede(): void
    {
        $palomar = $this->sede('Palomar');
        $varela = $this->sede('Varela');
        $ajeno = Gasto::query()->create(['sede_id' => $varela->id, 'fecha' => now(), 'tipo' => 'otro', 'monto' => 10]);
        $propio = Gasto::query()->create(['sede_id' => $palomar->id, 'fecha' => now(), 'tipo' => 'otro', 'monto' => 20]);
        $responsable = $this->usuario('Responsable');
        $this->asignarRol($responsable->persona, 'responsable_de_sede', 'sede', $palomar);
        Sanctum::actingAs($responsable->fresh());

        $this->assertSame([$propio->id], collect($this->getJson('/api/v1/gastos')->json('data'))->pluck('id')->all());
        $this->getJson("/api/v1/gastos/{$ajeno->id}")->assertForbidden();
        $this->deleteJson("/api/v1/gastos/{$propio->id}")->assertForbidden(); // no tiene gastos.delete
        $this->putJson("/api/v1/gastos/{$propio->id}", ['fecha' => now()->toDateString(), 'tipo' => 'otro', 'monto' => 25, 'sede_id' => $varela->id])->assertForbidden();
    }
}
