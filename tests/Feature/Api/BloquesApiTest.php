<?php

namespace Tests\Feature\Api;

use App\Models\Bloque;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

class BloquesApiTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_coordinador_crea_edita_y_agrega_horarios_en_su_sede(): void
    {
        $palomar = $this->sede('Palomar');
        $varela = $this->sede('Varela');
        $coord = $this->usuario('Coord');
        $this->asignarRol($coord->persona, 'coordinador', 'sede', $palomar);
        Sanctum::actingAs($coord->fresh());
        $base = ['nombre' => '1° A', 'anio' => 1, 'cantidad_max_alumnos' => 25, 'tambores' => ['Repique', 'Fondo Grave']];

        $this->postJson('/api/v1/bloques', $base + ['sede_id' => $varela->id])->assertForbidden();
        $this->postJson('/api/v1/bloques', ['sede_id' => $palomar->id])->assertStatus(422)->assertJsonValidationErrors(['nombre', 'anio', 'cantidad_max_alumnos']);

        $id = $this->postJson('/api/v1/bloques', $base + ['sede_id' => $palomar->id])->assertCreated()
            ->assertJsonPath('data.anio', 1)->assertJsonPath('data.tambores.1', 'Fondo Grave')->assertJsonPath('data.acciones.editar', true)->json('data.id');

        $this->postJson("/api/v1/bloques/{$id}/horarios", ['dia_semana' => 2, 'hora_inicio' => '19:00', 'hora_fin' => '18:00'])
            ->assertStatus(422)->assertJsonValidationErrors('hora_fin');
        $horario = $this->postJson("/api/v1/bloques/{$id}/horarios", ['dia_semana' => 2, 'hora_inicio' => '18:00', 'hora_fin' => '19:30'])->assertCreated()->json('data.id');
        $this->getJson("/api/v1/bloques/{$id}")->assertOk()->assertJsonPath('data.horarios_detalle.0.inicio', '18:00')->assertJsonPath('data.horarios_detalle.0.dia_nombre', 'Martes');

        $this->putJson("/api/v1/bloques/{$id}", $base + ['sede_id' => $varela->id])->assertForbidden();
        $this->putJson("/api/v1/bloques/{$id}", ['nombre' => '1° B'] + $base + ['sede_id' => $palomar->id, 'activo' => false])->assertOk()
            ->assertJsonPath('data.nombre', '1° B')->assertJsonPath('data.activo', false);

        $this->deleteJson("/api/v1/bloque-horarios/{$horario}")->assertOk();
        $this->assertSame(0, Bloque::query()->find($id)->horarios()->count());

        $ids = collect($this->getJson('/api/v1/bloques?incluir_inactivos=1')->json('data'))->pluck('id')->all();
        $this->assertContains($id, $ids);
        $this->assertNotContains($id, collect($this->getJson('/api/v1/bloques')->json('data'))->pluck('id')->all());
    }

    public function test_no_se_gestiona_un_bloque_ajeno_por_id_y_baja_segura(): void
    {
        $ajeno = $this->bloque($this->sede('Varela'), 'Ajeno');
        $coord = $this->usuario('Coord');
        $this->asignarRol($coord->persona, 'coordinador', 'sede', $this->sede('Palomar'));
        Sanctum::actingAs($coord->fresh());

        $this->getJson("/api/v1/bloques/{$ajeno->id}")->assertForbidden();
        $this->postJson("/api/v1/bloques/{$ajeno->id}/horarios", ['dia_semana' => 1, 'hora_inicio' => '10:00', 'hora_fin' => '11:00'])->assertForbidden();
        $this->deleteJson("/api/v1/bloques/{$ajeno->id}")->assertForbidden();

        Sanctum::actingAs($this->admin());
        $this->inscribirAlumno($this->persona('Ana'), $ajeno);
        $this->deleteJson("/api/v1/bloques/{$ajeno->id}")->assertStatus(422);
        $this->assertNotNull(Bloque::query()->find($ajeno->id));
    }
}
