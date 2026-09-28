<?php

namespace Tests\Feature\Api;

use App\Models\Evento;
use App\Models\Sede;
use App\Models\Show;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

class SedesEventosShowsApiTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_sedes_alta_edicion_y_baja_segura(): void
    {
        Sanctum::actingAs($this->admin());

        $id = $this->postJson('/api/v1/sedes', ['nombre' => 'Palomar', 'direccion' => 'Calle 1'])->assertCreated()
            ->assertJsonPath('data.activo', true)
            ->assertJsonPath('data.tipo_propiedad', 'alquilada')
            ->assertJsonPath('data.liquidacion_porc_docente', 40)->json('data.id');
        $this->postJson('/api/v1/sedes', ['nombre' => 'Palomar'])->assertStatus(422)->assertJsonValidationErrors('nombre');

        $this->putJson("/api/v1/sedes/{$id}", ['nombre' => 'Palomar', 'tipo_propiedad' => 'propia', 'liquidacion_porc_docente' => 60])->assertOk();
        $this->putJson("/api/v1/sedes/{$id}", ['nombre' => 'Palomar Centro', 'activo' => false])->assertOk()
            ->assertJsonPath('data.nombre', 'Palomar Centro')->assertJsonPath('data.activo', false)
            ->assertJsonPath('data.tipo_propiedad', 'propia')->assertJsonPath('data.liquidacion_porc_docente', 60);

        $this->bloque(Sede::query()->find($id));
        $this->deleteJson("/api/v1/sedes/{$id}")->assertStatus(422);
        $this->assertNotNull(Sede::query()->find($id));

        $this->assertSame(1, $this->getJson('/api/v1/sedes?gestion=1')->assertOk()->json('meta.total'));
        $this->assertSame([], $this->getJson('/api/v1/sedes')->json('data'), 'el catálogo solo lista sedes activas');
    }

    public function test_coordinador_edita_su_sede_pero_no_crea_ni_toca_otras(): void
    {
        $palomar = $this->sede('Palomar');
        $varela = $this->sede('Varela');
        $coord = $this->usuario('Coord');
        $this->asignarRol($coord->persona, 'coordinador', 'sede', $palomar);
        Sanctum::actingAs($coord->fresh());

        $this->postJson('/api/v1/sedes', ['nombre' => 'Nueva'])->assertForbidden();
        $this->putJson("/api/v1/sedes/{$palomar->id}", ['nombre' => 'Palomar 2'])->assertOk()->assertJsonPath('data.acciones.eliminar', false);
        $this->getJson("/api/v1/sedes/{$varela->id}")->assertForbidden();
        $this->putJson("/api/v1/sedes/{$varela->id}", ['nombre' => 'Hack'])->assertForbidden();
        $this->assertSame(['Palomar 2'], collect($this->getJson('/api/v1/sedes?gestion=1')->json('data'))->pluck('nombre')->all());
    }

    public function test_eventos_crud_con_ambito_y_validaciones(): void
    {
        $palomar = $this->sede('Palomar');
        $varela = $this->sede('Varela');
        $coord = $this->usuario('Coord');
        $this->asignarRol($coord->persona, 'coordinador', 'sede', $palomar);
        Sanctum::actingAs($coord->fresh());
        $base = ['titulo' => 'Muestra', 'fecha' => now()->addWeek()->toDateString(), 'tipo_evento' => 'muestra'];

        $this->postJson('/api/v1/eventos', $base + ['hora_inicio' => '19:00', 'hora_fin' => '18:00', 'sede_id' => $palomar->id])
            ->assertStatus(422)->assertJsonValidationErrors('hora_fin');
        $this->postJson('/api/v1/eventos', $base)->assertForbidden();
        $this->postJson('/api/v1/eventos', $base + ['sede_id' => $varela->id])->assertForbidden();

        $id = $this->postJson('/api/v1/eventos', $base + ['sede_id' => $palomar->id, 'hora_inicio' => '18:00'])->assertCreated()
            ->assertJsonPath('data.hora_inicio', '18:00')->assertJsonPath('data.ambito', 'sede')->assertJsonPath('data.acciones.editar', true)->json('data.id');

        $this->putJson("/api/v1/eventos/{$id}", $base + ['sede_id' => $varela->id])->assertForbidden();
        $this->putJson("/api/v1/eventos/{$id}", ['titulo' => 'Muestra final'] + $base + ['sede_id' => $palomar->id])->assertOk()->assertJsonPath('data.titulo', 'Muestra final');
        $this->assertSame(1, $this->getJson('/api/v1/eventos')->assertOk()->json('meta.total'));
        $this->deleteJson("/api/v1/eventos/{$id}")->assertOk();
        $this->assertNull(Evento::query()->find($id));
    }

    public function test_evento_de_otra_sede_no_se_ve_ni_se_borra_por_id(): void
    {
        $varela = $this->sede('Varela');
        $evento = Evento::query()->create(['titulo' => 'Ajeno', 'fecha' => now()->addDay()->toDateString(), 'tipo_evento' => 'otro', 'sede_id' => $varela->id]);
        $coord = $this->usuario('Coord');
        $this->asignarRol($coord->persona, 'coordinador', 'sede', $this->sede('Palomar'));
        Sanctum::actingAs($coord->fresh());

        $this->getJson("/api/v1/eventos/{$evento->id}")->assertForbidden();
        $this->deleteJson("/api/v1/eventos/{$evento->id}")->assertForbidden();
        $this->assertSame(0, $this->getJson('/api/v1/eventos')->json('meta.total'));
    }

    public function test_shows_convocan_solo_bloques_del_alcance(): void
    {
        $palomar = $this->sede('Palomar');
        $propio = $this->bloque($palomar, 'Propio');
        $ajeno = $this->bloque($this->sede('Varela'), 'Ajeno');
        $coord = $this->usuario('Coord');
        $this->asignarRol($coord->persona, 'coordinador', 'sede', $palomar);
        Sanctum::actingAs($coord->fresh());
        $base = ['titulo' => 'Carnaval', 'fecha' => now()->addMonth()->toDateString(), 'hora_inicio' => '20:00', 'lugar' => 'Plaza'];

        $this->postJson('/api/v1/shows', $base)->assertForbidden();
        $this->postJson('/api/v1/shows', $base + ['bloque_ids' => [$propio->id, $ajeno->id]])->assertForbidden();
        $id = $this->postJson('/api/v1/shows', $base + ['bloque_ids' => [$propio->id], 'convocatoria_abierta' => true])->assertCreated()
            ->assertJsonPath('data.hora_inicio', '20:00')->assertJsonPath('data.convocatoria_abierta', true)
            ->assertJsonPath('data.bloques.0.nombre', 'Propio')->json('data.id');

        $this->putJson("/api/v1/shows/{$id}", ['titulo' => 'Carnaval 2026'] + $base + ['bloque_ids' => [$propio->id]])->assertOk()->assertJsonPath('data.titulo', 'Carnaval 2026');
        $this->assertSame(1, $this->getJson('/api/v1/shows?proximos=1')->assertOk()->json('meta.total'));

        $ajenoShow = Show::query()->create(['titulo' => 'Otro', 'fecha' => now()->addMonth()->toDateString()]);
        $ajenoShow->bloques()->sync([$ajeno->id]);
        $this->deleteJson("/api/v1/shows/{$ajenoShow->id}")->assertForbidden();
        $this->deleteJson("/api/v1/shows/{$id}")->assertOk();
    }

    public function test_sin_permiso_de_shows_es_403(): void
    {
        $user = $this->usuario('Contador');
        $this->asignarRol($user->persona, 'contador');
        Sanctum::actingAs($user->fresh());

        $this->getJson('/api/v1/shows')->assertForbidden();
        $this->getJson('/api/v1/eventos')->assertForbidden();
    }
}
