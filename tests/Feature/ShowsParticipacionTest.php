<?php

namespace Tests\Feature;

use App\Domain\Agenda\AvisosShows;
use App\Models\InventarioItem;
use App\Models\ProgramaRitmo;
use App\Models\Show;
use App\Models\ShowConfirmacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

/** Shows: confirmación de cada alumno, temas, ensayos con asistencia, logística, instrumentos y avisos. */
class ShowsParticipacionTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    /** @return array{0: Show, 1: \App\Models\User, 2: \App\Models\Alumno, 3: \App\Models\Bloque} */
    private function escenario(): array
    {
        $bloque = $this->bloque($this->sede('Palomar'), 'Bloque A');
        $ana = $this->usuario('Ana');
        $alumna = $this->inscribirAlumno($ana->persona, $bloque);
        $show = Show::query()->create(['titulo' => 'Carnaval', 'fecha' => now()->addDays(10)->toDateString(), 'hora_inicio' => '20:00', 'lugar' => 'Plaza']);
        $show->bloques()->sync([$bloque->id]);

        return [$show, $ana, $alumna, $bloque];
    }

    public function test_el_alumno_convocado_confirma_desde_la_app_y_el_organizador_ve_el_estado(): void
    {
        [$show, $ana, $alumna, $bloque] = $this->escenario();
        $beto = $this->usuario('Beto');
        $this->inscribirAlumno($beto->persona, $bloque);
        $ajena = $this->usuario('Ajena');
        $this->inscribirAlumno($ajena->persona, $this->bloque($this->sede('Varela')));

        Sanctum::actingAs($ana);
        $this->getJson('/api/v1/mi/shows')->assertOk()->assertJsonPath('data.0.titulo', 'Carnaval')->assertJsonPath('data.0.mi_respuesta', null);
        $this->postJson("/api/v1/mi/shows/{$show->id}/confirmacion", ['respuesta' => 'quizas'])->assertStatus(422);
        $this->postJson("/api/v1/mi/shows/{$show->id}/confirmacion", ['respuesta' => 'voy', 'comentario' => 'Llevo el surdo'])
            ->assertOk()->assertJsonPath('data.mi_respuesta', 'voy');

        Sanctum::actingAs($ajena);
        $this->getJson('/api/v1/mi/shows')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson("/api/v1/mi/shows/{$show->id}/confirmacion", ['respuesta' => 'voy'])->assertForbidden();

        Sanctum::actingAs($this->admin());
        $r = $this->getJson("/api/v1/shows/{$show->id}/convocados")->assertOk();
        $this->assertSame(['convocados' => 2, 'voy' => 1, 'tal_vez' => 0, 'no_voy' => 0, 'sin_respuesta' => 1, 'asistieron' => 0], $r->json('resumen'));
        $this->getJson("/api/v1/shows/{$show->id}")->assertOk()->assertJsonPath('data.confirmaciones.voy', 1);

        $this->putJson("/api/v1/shows/{$show->id}/asistencia", ['marcas' => [$alumna->id => true, 999999 => true]])
            ->assertOk()->assertJsonPath('resumen.asistieron', 1);
    }

    public function test_el_docente_del_bloque_ve_confirmaciones_y_toma_asistencia_pero_no_edita(): void
    {
        [$show, , $alumna, $bloque] = $this->escenario();
        $profe = $this->usuario('Profe');
        $this->asignarDocente($profe->persona, $bloque);
        Sanctum::actingAs($profe->fresh());

        $this->getJson("/api/v1/shows/{$show->id}/convocados")->assertOk()->assertJsonPath('resumen.convocados', 1);
        $this->putJson("/api/v1/shows/{$show->id}/asistencia", ['marcas' => [$alumna->id => false]])->assertOk();
        $this->putJson("/api/v1/shows/{$show->id}/temas", ['temas' => [['titulo' => 'X']]])->assertForbidden();
    }

    public function test_temas_ensayos_logistica_e_instrumentos(): void
    {
        [$show, $ana, $alumna] = $this->escenario();
        $toque = ProgramaRitmo::query()->create(['slug' => 'candombe', 'nombre' => 'Candombe', 'año' => 1, 'orden' => 1]);
        $surdo = InventarioItem::query()->create(['sede_id' => $this->sede('Depósito')->id, 'tipo' => 'instrumento', 'nombre' => 'Surdo 22', 'cantidad' => 1, 'propietario_tipo' => 'escuela', 'estado' => 'bueno']);
        Sanctum::actingAs($this->admin());

        $this->putJson("/api/v1/shows/{$show->id}/temas", ['temas' => [['programa_ritmo_id' => $toque->id], ['titulo' => 'Murga final', 'notas' => 'cierre']]])
            ->assertOk()->assertJsonPath('data.temas.0.nombre', 'Candombe')->assertJsonPath('data.temas.1.nombre', 'Murga final');
        $this->putJson("/api/v1/shows/{$show->id}/temas", ['temas' => [['notas' => 'sin nombre']]])->assertStatus(422);
        $this->putJson("/api/v1/shows/{$show->id}/logistica", ['punto_encuentro' => 'Estación Palomar', 'hora_encuentro' => '18:30', 'transporte' => 'Micro'])
            ->assertOk()->assertJsonPath('data.logistica.hora_encuentro', '18:30');
        $this->getJson('/api/v1/shows/plan-catalogo')->assertOk()->assertJsonPath('instrumentos.0.nombre', 'Surdo 22');
        $this->putJson("/api/v1/shows/{$show->id}/instrumentos", ['instrumentos' => [['inventario_item_id' => $surdo->id], ['descripcion' => 'Repiques', 'cantidad' => 4]]])
            ->assertOk()->assertJsonPath('data.instrumentos.1.cantidad', 4);

        $ensayo = $this->postJson("/api/v1/shows/{$show->id}/ensayos", ['fecha' => now()->addDays(3)->toDateString(), 'hora_inicio' => '15:00', 'lugar' => 'Sede'])
            ->assertCreated()->json('data.id');
        $this->putJson("/api/v1/show-ensayos/{$ensayo}/asistencia", ['marcas' => [$alumna->id => true]])->assertOk()->assertJsonPath('data.asistencia.0.presente', true);
        $this->getJson("/api/v1/shows/{$show->id}")->assertJsonPath('data.ensayos.0.presentes', 1);

        // El alumno ve todo eso en su show.
        Sanctum::actingAs($ana);
        $this->getJson("/api/v1/mi/shows/{$show->id}")->assertOk()
            ->assertJsonPath('data.logistica.punto_encuentro', 'Estación Palomar')
            ->assertJsonCount(2, 'data.temas')->assertJsonCount(1, 'data.ensayos');
    }

    public function test_avisos_de_convocatoria_y_recordatorio_sin_repetir(): void
    {
        [$show, $ana, $alumna, $bloque] = $this->escenario();
        $beto = $this->usuario('Beto');
        $betoAlumno = $this->inscribirAlumno($beto->persona, $bloque);
        $avisos = app(AvisosShows::class);

        $this->assertSame(2, $avisos->convocados());
        $this->assertSame(0, $avisos->convocados(), 'no se repite');
        $n = $ana->fresh()->notifications()->first();
        $this->assertSame('Te convocaron: Carnaval', $n->data['titulo']);
        $this->assertSame("app://mis-shows/{$show->id}", $n->data['enlace']);

        ShowConfirmacion::query()->create(['show_id' => $show->id, 'alumno_id' => $betoAlumno->id, 'respuesta' => 'no_voy']);
        $this->assertSame(0, $avisos->recordatorios(), 'faltan más de 2 días');
        $show->update(['fecha' => now()->addDay()->toDateString()]);
        $this->assertSame(1, $avisos->recordatorios(), 'solo a quien no dijo que no va');
        $this->artisan('chilinga:avisos shows-recordatorio')->assertSuccessful();
    }

    public function test_pantallas_web_del_organizador_y_del_alumno(): void
    {
        [$show, $ana] = $this->escenario();
        $admin = $this->admin();
        $this->actingAs($admin)->get("/shows/{$show->id}")->assertOk()->assertSee('Confirmaciones y asistencia')->assertSee('Ana');
        $this->actingAs($admin)->post("/shows/{$show->id}/temas", ['titulo' => 'Final'])->assertRedirect();
        $this->actingAs($admin)->post("/shows/{$show->id}/ensayos", ['fecha' => now()->addDay()->toDateString()])->assertRedirect();
        $ensayo = $show->ensayos()->first();
        $this->actingAs($admin)->get("/show-ensayos/{$ensayo->id}")->assertOk()->assertSee('Asistencia al ensayo');

        $this->actingAs($ana)->get('/mis-shows')->assertOk()->assertSee('Carnaval')->assertSee('Final');
        $this->actingAs($ana)->post("/mis-shows/{$show->id}/confirmacion", ['respuesta' => 'tal_vez'])->assertRedirect('/mis-shows');
        $this->assertSame('tal_vez', ShowConfirmacion::query()->firstOrFail()->respuesta);
    }
}
