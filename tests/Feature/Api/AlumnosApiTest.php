<?php

namespace Tests\Feature\Api;

use App\Models\Alumno;
use App\Models\Asistencia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

class AlumnosApiTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function datos(int $sedeId, array $bloques, array $extra = []): array
    {
        return $extra + ['nombre_apellido' => 'Lucía Paz', 'fecha_nacimiento' => '2010-05-01', 'instrumento_principal' => 'Repique', 'sede_id' => $sedeId, 'bloque_ids' => $bloques];
    }

    public function test_inscribe_a_una_persona_existente_sin_duplicarla(): void
    {
        $sede = $this->sede('Palomar');
        $bloque = $this->bloque($sede);
        $docente = $this->persona('Juana', '30111222');
        $this->asignarDocente($docente, $this->bloque($sede, 'Otro'));
        $coord = $this->usuario('Coord');
        $this->asignarRol($coord->persona, 'coordinador', 'sede', $sede);
        Sanctum::actingAs($coord->fresh());

        $r = $this->postJson('/api/v1/alumnos', $this->datos($sede->id, [$bloque->id], ['persona_id' => $docente->id, 'nombre_apellido' => 'Juana', 'dni' => '30111222']))
            ->assertCreated()->assertJsonPath('data.persona_id', $docente->id)->assertJsonPath('data.es_docente', true)->assertJsonPath('data.bloques_detalle.0.principal', true);
        $this->assertSame(1, Alumno::query()->where('persona_id', $docente->id)->count());

        $this->postJson('/api/v1/alumnos', $this->datos($sede->id, [$bloque->id], ['persona_id' => $docente->id]))
            ->assertStatus(422)->assertJsonValidationErrors('persona_id');
        $this->postJson('/api/v1/alumnos', $this->datos($sede->id, [$bloque->id], ['dni' => '30.111.222']))
            ->assertStatus(422)->assertJsonValidationErrors('dni');
        $this->assertIsInt($r->json('data.id'));
    }

    public function test_no_inscribe_en_sedes_o_bloques_fuera_del_alcance(): void
    {
        $palomar = $this->sede('Palomar');
        $varela = $this->sede('Varela');
        $coord = $this->usuario('Coord');
        $this->asignarRol($coord->persona, 'coordinador', 'sede', $palomar);
        Sanctum::actingAs($coord->fresh());

        $this->postJson('/api/v1/alumnos', $this->datos($varela->id, []))->assertForbidden();
        $this->postJson('/api/v1/alumnos', $this->datos($palomar->id, [$this->bloque($varela)->id]))->assertForbidden();
        $this->postJson('/api/v1/alumnos', $this->datos($palomar->id, [], ['fecha_nacimiento' => null]))->assertStatus(422)->assertJsonValidationErrors('fecha_nacimiento');
        $this->assertSame(0, Alumno::query()->count());
    }

    public function test_edicion_parcial_conserva_bloques_y_baja_segura(): void
    {
        $sede = $this->sede('Palomar');
        $a = $this->bloque($sede, 'A');
        $b = $this->bloque($sede, 'B');
        Sanctum::actingAs($this->admin());
        $id = $this->postJson('/api/v1/alumnos', $this->datos($sede->id, [$a->id, $b->id], ['bloque_principal_id' => $b->id]))->assertCreated()->json('data.id');

        $this->putJson("/api/v1/alumnos/{$id}", ['nombre_apellido' => 'Lucía María Paz', 'fecha_nacimiento' => '2010-05-01', 'instrumento_principal' => 'Medio', 'sede_id' => $sede->id])
            ->assertOk()->assertJsonPath('data.nombre', 'Lucía María Paz')->assertJsonCount(2, 'data.bloques_detalle')->assertJsonPath('data.bloque_principal_id', $b->id);
        $this->getJson('/api/v1/personas/'.Alumno::query()->find($id)->persona_id)->assertJsonPath('data.nombre', 'Lucía María Paz');

        Asistencia::query()->create(['alumno_id' => $id, 'bloque_id' => $a->id, 'fecha' => now()->toDateString(), 'presente' => true, 'tipo_asistencia' => 'presente']);
        $this->deleteJson("/api/v1/alumnos/{$id}")->assertStatus(422);
        $this->assertNotNull(Alumno::query()->find($id));
    }
}
