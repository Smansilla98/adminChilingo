<?php

namespace Tests\Feature;

use App\Models\AlumnoAnio;
use App\Models\ProgramaRitmo;
use App\Models\ProgresoToque;
use App\Models\PromocionAnio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

class ProgresoToquesTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Las migraciones cargan el programa real: estos tests arman el suyo.
        \Illuminate\Support\Facades\DB::table('programa_ritmos')->delete();
    }

    private function toque(string $nombre, int $anio, int $orden, bool $opcional = false): ProgramaRitmo
    {
        return ProgramaRitmo::query()->create(['slug' => \Illuminate\Support\Str::slug($nombre), 'año' => $anio, 'orden' => $orden, 'nombre' => $nombre, 'opcional' => $opcional, 'publicado' => true]);
    }

    public function test_docente_marca_toques_se_propone_el_paso_de_anio_y_se_confirma_con_historial(): void
    {
        $sede = $this->sede('Palomar');
        $bloque = $this->bloque($sede, 'Bloque 1', 1);
        $userAlumno = $this->usuario('Ana');
        $alumno = $this->inscribirAlumno($userAlumno->persona, $bloque);
        $ajeno = $this->inscribirAlumno($this->persona('Ajeno'), $this->bloque($this->sede('Varela'), 'Otro'));
        $profe = $this->usuario('Profe');
        $this->asignarDocente($profe->persona, $bloque);
        $a = $this->toque('Chilinga 1', 1, 1);
        $b = $this->toque('Candombe', 1, 2);
        $op = $this->toque('Opcional', 1, 3, true);
        $this->toque('Murga', 2, 1);

        Sanctum::actingAs($profe->fresh());
        $m = $this->getJson("/api/v1/progreso/bloques/{$bloque->id}")->assertOk();
        $this->assertCount(3, $m->json('toques'));
        $this->assertSame(1, $m->json('alumnos.0.anio_actual'));
        $this->assertTrue($m->json('puede_editar'));

        // No se puede tocar a alguien de otro bloque ni otro bloque.
        $this->postJson("/api/v1/progreso/bloques/{$bloque->id}/estado", ['alumno_id' => $ajeno->id, 'programa_ritmo_id' => $a->id, 'estado' => 'aprobado'])->assertStatus(422);
        $this->getJson('/api/v1/progreso/bloques/'.$ajeno->bloque_id)->assertForbidden();

        $this->postJson("/api/v1/progreso/bloques/{$bloque->id}/estado", ['alumno_id' => $alumno->id, 'programa_ritmo_id' => $a->id, 'estado' => 'aprobado'])
            ->assertOk()->assertJsonPath('propuesta', null);
        // Con el repaso «lo toca» se completa el último obligatorio: el opcional no hace falta.
        $this->postJson('/api/v1/progreso/evaluaciones', [
            'alumno_id' => $alumno->id, 'bloque_id' => $bloque->id, 'fecha' => now()->toDateString(),
            'toques' => [$b->id], 'resultado' => 'lo_toca', 'devolucion' => 'Muy bien el candombe.', 'visible_alumno' => true,
        ])->assertCreated();
        $this->assertSame(ProgresoToque::APROBADO, ProgresoToque::query()->where('alumno_id', $alumno->id)->where('programa_ritmo_id', $b->id)->value('estado'));
        $this->assertSame(ProgresoToque::NO_EMPEZADO, ProgresoToque::query()->where('alumno_id', $alumno->id)->where('programa_ritmo_id', $op->id)->value('estado') ?? 'no_empezado');

        $propuesta = PromocionAnio::query()->where('alumno_id', $alumno->id)->firstOrFail();
        $this->assertSame(PromocionAnio::PROPUESTA, $propuesta->estado);
        $this->assertSame(2, $propuesta->anio_hasta);

        // El alumno ve su progreso y la devolución visible, pero no puede confirmar.
        Sanctum::actingAs($userAlumno->fresh());
        $mio = $this->getJson('/api/v1/mi/progreso')->assertOk();
        $this->assertSame(1, $mio->json('data.0.anio_actual'));
        $this->assertSame('Muy bien el candombe.', $mio->json('data.0.evaluaciones.0.devolucion'));
        $this->postJson("/api/v1/progreso/promociones/{$propuesta->id}/confirmar")->assertForbidden();

        Sanctum::actingAs($profe->fresh());
        $this->postJson("/api/v1/progreso/promociones/{$propuesta->id}/confirmar")->assertOk()->assertJsonPath('data.estado', 'confirmada');
        $this->postJson("/api/v1/progreso/promociones/{$propuesta->id}/confirmar")->assertStatus(422);
        $this->assertSame([1, 2], AlumnoAnio::query()->where('alumno_id', $alumno->id)->orderBy('id')->pluck('anio')->all());
        $this->assertNull(AlumnoAnio::query()->where('alumno_id', $alumno->id)->where('anio', 2)->value('hasta'));

        $this->getJson("/api/v1/progreso/alumnos/{$alumno->id}")->assertOk()->assertJsonPath('anio_actual', 2)->assertJsonCount(2, 'historial');
    }

    public function test_si_deja_de_cumplir_se_retira_la_propuesta_y_descartar_no_la_regenera(): void
    {
        $bloque = $this->bloque($this->sede('Quilmes'));
        $alumno = $this->inscribirAlumno($this->persona('Beto'), $bloque);
        $profe = $this->usuario('Profe');
        $this->asignarDocente($profe->persona, $bloque);
        $t = $this->toque('Único', 1, 1);
        Sanctum::actingAs($profe->fresh());

        $url = "/api/v1/progreso/bloques/{$bloque->id}/estado";
        $this->postJson($url, ['alumno_id' => $alumno->id, 'programa_ritmo_id' => $t->id, 'estado' => 'aprobado'])->assertJsonPath('propuesta.anio_hasta', 2);
        $this->postJson($url, ['alumno_id' => $alumno->id, 'programa_ritmo_id' => $t->id, 'estado' => 'en_proceso'])->assertJsonPath('propuesta', null);
        $id = $this->postJson($url, ['alumno_id' => $alumno->id, 'programa_ritmo_id' => $t->id, 'estado' => 'aprobado'])->json('propuesta.id');
        $this->postJson("/api/v1/progreso/promociones/{$id}/descartar")->assertOk();
        $this->postJson($url, ['alumno_id' => $alumno->id, 'programa_ritmo_id' => $t->id, 'estado' => 'aprobado'])->assertJsonPath('propuesta', null);
    }

    public function test_web_grilla_reporte_y_alcance_por_sede(): void
    {
        $palomar = $this->sede('Palomar');
        $bloque = $this->bloque($palomar, 'Bloque P', 1);
        $otro = $this->bloque($this->sede('Varela'), 'Bloque V', 1);
        $alumno = $this->inscribirAlumno($this->persona('Caro'), $bloque);
        $t = $this->toque('Chilinga 1', 1, 1);
        $coord = $this->usuario('Coord');
        $this->asignarRol($coord->persona, 'coordinador', 'sede', $palomar);

        $this->actingAs($coord->fresh())->get('/progreso')->assertOk()->assertSee('Bloque P')->assertDontSee('Bloque V');
        $this->get("/progreso/bloques/{$bloque->id}")->assertOk()->assertSee('Chilinga 1')->assertSee('Caro');
        $this->get("/progreso/bloques/{$otro->id}")->assertForbidden();
        $this->post("/progreso/bloques/{$bloque->id}", ['estados' => [$alumno->id => [$t->id => 'aprobado']]])->assertRedirect();
        $this->assertSame('aprobado', ProgresoToque::query()->where('alumno_id', $alumno->id)->value('estado'));
        $this->get('/progreso/reporte')->assertOk()->assertSee('Bloque P')->assertSee('100 %');
        $this->get("/progreso/alumnos/{$alumno->id}")->assertOk()->assertSee('Lo toca con el bloque');

        // Un alumno no entra a la gestión.
        $this->actingAs($this->usuario('Nadie'))->get('/progreso')->assertForbidden();
    }
}
