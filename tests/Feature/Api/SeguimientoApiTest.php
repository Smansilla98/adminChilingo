<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

class SeguimientoApiTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_docente_escribe_notas_y_el_alumno_ve_solo_las_visibles(): void
    {
        $sede = $this->sede('Palomar');
        $bloque = $this->bloque($sede);
        $userAlumno = $this->usuario('Ana');
        $alumno = $this->inscribirAlumno($userAlumno->persona, $bloque);
        $ajeno = $this->inscribirAlumno($this->persona('Ajeno'), $this->bloque($this->sede('Varela')));
        $profe = $this->usuario('Profe');
        $this->asignarDocente($profe->persona, $bloque);
        Sanctum::actingAs($profe->fresh());

        $base = ['fecha' => now()->toDateString(), 'tipo' => 'avance', 'eje' => 'ritmo', 'cuerpo' => 'Mejoró el toque.'];
        $this->postJson('/api/v1/seguimiento', ['alumno_id' => $ajeno->id] + $base)->assertForbidden();
        $this->postJson('/api/v1/seguimiento', ['alumno_id' => $alumno->id, 'tipo' => 'x', 'fecha' => now()->toDateString()])->assertStatus(422)->assertJsonValidationErrors(['tipo', 'cuerpo']);
        $this->postJson('/api/v1/seguimiento', ['alumno_id' => $alumno->id, 'visible_alumno' => true] + $base)->assertCreated();
        $privada = $this->postJson('/api/v1/seguimiento', ['alumno_id' => $alumno->id, 'cuerpo' => 'Nota interna'] + $base)->assertCreated()->json('data.id');

        $this->getJson("/api/v1/alumnos/{$alumno->id}/seguimiento")->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('puede_escribir', true);

        Sanctum::actingAs($userAlumno->fresh());
        $r = $this->getJson("/api/v1/alumnos/{$alumno->id}/seguimiento")->assertOk();
        $this->assertCount(1, $r->json('data'));
        $this->assertFalse($r->json('puede_escribir'));

        Sanctum::actingAs($profe->fresh());
        $this->deleteJson("/api/v1/seguimiento/{$privada}")->assertOk();
        $this->getJson("/api/v1/alumnos/{$ajeno->id}/seguimiento")->assertForbidden();
    }
}
