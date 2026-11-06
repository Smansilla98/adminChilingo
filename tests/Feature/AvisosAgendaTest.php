<?php

namespace Tests\Feature;

use App\Domain\Notificaciones\Avisos;
use App\Domain\Notificaciones\ConfigAvisos;
use App\Models\Asistencia;
use App\Models\BloqueHorario;
use App\Models\Evento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\Escenarios;
use Tests\TestCase;

/** Recordatorio de pasar lista, avisos de eventos nuevos y próximos, y convocatorias. */
class AvisosAgendaTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_recuerda_pasar_lista_a_los_docentes_si_la_clase_no_tiene_asistencia(): void
    {
        Carbon::setTestNow(Carbon::parse('next tuesday 18:00'));
        $bloque = $this->bloque($this->sede('Palomar'), 'Bloque martes');
        $profe = $this->usuario('Profe');
        $this->asignarDocente($profe->persona, $bloque);
        $otro = $this->usuario('Profe de otro bloque');
        $this->asignarDocente($otro->persona, $this->bloque($this->sede('Varela')));
        BloqueHorario::query()->create(['bloque_id' => $bloque->id, 'dia_semana' => 2, 'hora_inicio' => '18:00', 'hora_fin' => '20:00']);

        $avisos = app(Avisos::class);
        $this->assertSame(0, $avisos->pasarLista(), 'recién empieza: todavía no');

        Carbon::setTestNow(now()->addMinutes(31));
        $this->assertSame(1, $avisos->pasarLista());
        $this->assertSame(0, $avisos->pasarLista(), 'una sola vez por clase');

        $n = $profe->fresh()->notifications()->first();
        $this->assertSame('Pasá lista: Bloque martes', $n->data['titulo']);
        $this->assertStringContainsString('18:00', $n->data['mensaje']);
        $this->assertSame("app://asistencia/{$bloque->id}", $n->data['enlace']);
        $this->assertSame(0, $otro->fresh()->notifications()->count());
    }

    public function test_no_recuerda_pasar_lista_si_ya_se_tomo_o_si_la_clase_termino_hace_horas(): void
    {
        Carbon::setTestNow(Carbon::parse('next tuesday 18:45'));
        $bloque = $this->bloque($this->sede('Palomar'));
        $profe = $this->usuario('Profe');
        $this->asignarDocente($profe->persona, $bloque);
        $alumno = $this->inscribirAlumno($this->persona('Alumna'), $bloque);
        BloqueHorario::query()->create(['bloque_id' => $bloque->id, 'dia_semana' => 2, 'hora_inicio' => '18:00', 'hora_fin' => '20:00']);
        Asistencia::query()->create(['alumno_id' => $alumno->id, 'bloque_id' => $bloque->id, 'fecha' => today(), 'presente' => true]);

        $this->assertSame(0, app(Avisos::class)->pasarLista());

        Asistencia::query()->delete();
        Carbon::setTestNow(Carbon::parse('next tuesday 23:30'));
        $this->assertSame(0, app(Avisos::class)->pasarLista());
        $this->assertSame(0, $profe->fresh()->notifications()->count());
    }

    public function test_un_evento_nuevo_de_un_bloque_avisa_a_sus_alumnos_y_docentes(): void
    {
        $bloque = $this->bloque($this->sede('Palomar'));
        $alumna = $this->usuario('Alumna');
        $this->inscribirAlumno($alumna->persona, $bloque);
        $profe = $this->usuario('Profe');
        $this->asignarDocente($profe->persona, $bloque);
        $ajena = $this->usuario('De otra sede');
        $this->inscribirAlumno($ajena->persona, $this->bloque($this->sede('Varela')));
        Evento::query()->create(['titulo' => 'Muestra de fin de año', 'fecha' => now()->addDays(10), 'hora_inicio' => '19:30', 'bloque_id' => $bloque->id, 'tipo_evento' => 'muestra']);
        Evento::query()->create(['titulo' => 'Aniversario', 'fecha' => now()->addDays(10), 'tipo_evento' => 'aniversario']); // general: solo en la agenda

        $this->assertSame(2, app(Avisos::class)->eventosNuevos());
        $this->assertSame(0, app(Avisos::class)->eventosNuevos());

        $n = $alumna->fresh()->notifications()->sole();
        $this->assertSame('Nuevo evento: Muestra de fin de año', $n->data['titulo']);
        $this->assertStringContainsString('19:30 · Palomar', $n->data['mensaje']);
        $this->assertSame(1, $profe->fresh()->notifications()->count());
        $this->assertSame(0, $ajena->fresh()->notifications()->count());
    }

    public function test_una_convocatoria_de_toda_la_escuela_llega_a_todas_las_cuentas(): void
    {
        $alumna = $this->usuario('Alumna');
        $this->inscribirAlumno($alumna->persona, $this->bloque($this->sede('Palomar')));
        $profe = $this->usuario('Profe');
        $admin = $this->admin();
        $baja = $this->usuario('Dada de baja');
        $baja->update(['activo' => false]);
        Evento::query()->create(['titulo' => 'Marcha federal', 'descripcion' => 'Nos juntamos 17:30 en Congreso con los tambores.',
            'fecha' => now()->addDays(4), 'hora_inicio' => '18:00', 'tipo_evento' => 'convocatoria']);

        app(Avisos::class)->eventosNuevos();

        foreach ([$alumna, $profe, $admin] as $u) {
            $n = $u->fresh()->notifications()->sole();
            $this->assertSame('Convocatoria: Marcha federal', $n->data['titulo']);
            $this->assertStringContainsString('18:00. Nos juntamos 17:30 en Congreso', $n->data['mensaje']);
        }
        $this->assertSame(0, $baja->fresh()->notifications()->count());
    }

    public function test_el_recordatorio_de_evento_usa_los_dias_configurados(): void
    {
        ConfigAvisos::guardar(['evento_proximo' => ['dias' => 3]]);
        $bloque = $this->bloque($this->sede('Palomar'));
        $alumna = $this->usuario('Alumna');
        $this->inscribirAlumno($alumna->persona, $bloque);
        Evento::query()->create(['titulo' => 'Ensayo general', 'fecha' => now()->addDays(3), 'bloque_id' => $bloque->id, 'tipo_evento' => 'otro']);
        Evento::query()->create(['titulo' => 'Muy lejos', 'fecha' => now()->addDays(9), 'bloque_id' => $bloque->id, 'tipo_evento' => 'otro']);

        $this->assertSame(1, app(Avisos::class)->eventosProximos());
        $this->assertSame('Se viene: Ensayo general', $alumna->fresh()->notifications()->sole()->data['titulo']);
    }

    public function test_los_avisos_nuevos_se_configuran_desde_la_web(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('configuracion.avisos.edit'))->assertOk()
            ->assertSeeText('Recordatorio para pasar lista')->assertSeeText('Convocatoria')->assertSeeText('Minutos después de empezar la clase');

        $this->actingAs($admin)->put(route('configuracion.avisos.update'), ['avisos' => [
            'pasar_lista' => ['activo' => '1', 'canales' => ['', 'push'], 'minutos' => 45, 'titulo' => 'Lista: {bloque}', 'mensaje' => 'Falta la asistencia'],
        ]])->assertRedirect();

        $c = ConfigAvisos::de('pasar_lista');
        $this->assertSame(45, $c['minutos']);
        $this->assertSame(['push'], $c['canales']);
        $this->assertArrayNotHasKey('dias', $c);

        $this->actingAs($admin)->post(route('configuracion.avisos.probar'), ['tipo' => 'convocatoria'])->assertSessionHas('success');
        $this->assertStringContainsString('Marcha de ejemplo', $admin->fresh()->notifications()->first()->data['titulo']);
    }
}
