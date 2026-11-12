<?php

namespace Tests\Feature\Api;

use App\Models\LiquidacionDocenteMensual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

class GestionLiquidacionesApiTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_administracion_lista_genera_aprueba_y_paga_por_api(): void
    {
        $bloque = $this->bloque($this->sede('Palomar'));
        $profesor = $this->asignarDocente($this->persona('Docente'), $bloque);
        $tesorero = $this->usuario('Tesorera');
        $this->asignarRol($tesorero->persona, 'tesorero');
        Sanctum::actingAs($tesorero->fresh());

        $this->getJson('/api/v1/gestion/liquidaciones?anio=2026&mes=10')->assertOk()
            ->assertJsonPath('data.0.profesor.id', $profesor->id)
            ->assertJsonPath('data.0.liquidacion', null)
            ->assertJsonPath('puede_gestionar', true);

        $this->postJson('/api/v1/gestion/liquidaciones/generar', ['anio' => 2026, 'mes' => 10, 'profesor_id' => $profesor->id])
            ->assertOk()->assertJsonPath('data.estado', 'borrador');
        $liq = LiquidacionDocenteMensual::query()->where('profesor_id', $profesor->id)->firstOrFail();

        // Pagar antes de aprobar no se puede.
        $this->postJson("/api/v1/gestion/liquidaciones/{$liq->id}/pagar")->assertStatus(422);
        $this->postJson("/api/v1/gestion/liquidaciones/{$liq->id}/aprobar")->assertOk()->assertJsonPath('data.estado', 'aprobada');
        $this->postJson("/api/v1/gestion/liquidaciones/{$liq->id}/pagar", ['registrar_gasto' => false])->assertOk()->assertJsonPath('data.estado', 'pagada');
        $this->getJson('/api/v1/gestion/liquidaciones?anio=2026&mes=10')->assertJsonPath('data.0.liquidacion.estado', 'pagada');

        // Los módulos de G llegan a la app según el permiso (el tesorero ve liquidaciones y dirección).
        $claves = collect($this->getJson('/api/v1/me')->json('modulos'))->pluck('clave')->all();
        $this->assertContains('liquidaciones', $claves);
        $this->assertContains('direccion', $claves);
    }

    public function test_solo_ver_no_aprueba_y_el_docente_no_entra_a_la_gestion(): void
    {
        $bloque = $this->bloque($this->sede('Varela'));
        $profesor = $this->asignarDocente($this->persona('Docente'), $bloque);
        $liq = LiquidacionDocenteMensual::query()->create([
            'profesor_id' => $profesor->id, 'anio' => 2026, 'mes' => 9, 'modalidad' => 'porcentaje', 'estado' => 'borrador',
            'monto_total' => 0, 'monto_porcentaje' => 0, 'monto_horas' => 0,
        ]);

        $lector = $this->usuario('Lector');
        $this->asignarPermiso($lector->persona, 'liquidaciones.view');
        Sanctum::actingAs($lector->fresh());
        $this->getJson('/api/v1/gestion/liquidaciones?anio=2026&mes=9')->assertOk()->assertJsonPath('puede_gestionar', false);
        $this->postJson("/api/v1/gestion/liquidaciones/{$liq->id}/aprobar")->assertForbidden();

        $docente = $profesor->user ?? $this->usuario('Docente');
        Sanctum::actingAs($docente->fresh());
        $this->getJson('/api/v1/gestion/liquidaciones?anio=2026&mes=9')->assertForbidden();
    }
}
