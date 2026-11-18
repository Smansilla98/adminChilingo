<?php

namespace Tests\Feature;

use App\Models\ProgramaRitmo;
use App\Support\PartituraScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenarios;
use Tests\TestCase;

/** «Toques y recursos»: lista pública de toques publicados con recursos, sin datos de administración. */
class ProgramaRecursosTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function toque(string $slug, array $medios = [], array $extra = []): ProgramaRitmo
    {
        return ProgramaRitmo::query()->create(array_merge([
            'slug' => $slug, 'año' => 2, 'orden' => 1, 'nombre' => 'Toque '.$slug, 'publicado' => true, 'en_programa' => true,
            'medios' => $medios,
        ], $extra));
    }

    private function conGolpes(): array
    {
        return PartituraScore::normalizar([
            'title' => 'X', 'tempo' => 90, 'timeSignature' => ['num' => 4, 'den' => 4],
            'instruments' => [['id' => 'surdo_grave']],
            'sections' => [['name' => 'Toque', 'measures' => [['voces' => ['surdo_grave' => [['dur' => 'q', 'stroke' => 'nota']]]]]]],
        ]);
    }

    private function video(): array
    {
        return ['id' => 'vaaaaaaaa', 'instrumento' => 'timbal', 'titulo' => 'Timbal', 'orden' => 1, 'offset_seg' => 0, 'archivo' => 'partituras/videos/1-abcdefghijklmnop.mp4'];
    }

    public function test_lista_solo_toques_publicados_con_recursos(): void
    {
        $this->toque('con-video', ['videos_multipista' => [$this->video()]]);
        $this->toque('con-partitura', ['partitura_score' => $this->conGolpes()], ['año' => 3]);
        $this->toque('sin-recursos', []);
        $this->toque('borrador', ['videos_multipista' => [$this->video()]], ['publicado' => false]);
        $this->toque('fuera-del-programa', ['videos_multipista' => [$this->video()]], ['en_programa' => false]);

        $this->get(route('programa.recursos.index'))->assertOk()
            ->assertSee('Toque con-video')->assertSee('Toque con-partitura')
            ->assertDontSee('Toque sin-recursos')->assertDontSee('Toque borrador')->assertDontSee('Toque fuera-del-programa');
    }

    public function test_cuenta_los_recursos_de_cada_toque(): void
    {
        $this->toque('dos-videos', ['videos_multipista' => [$this->video(), array_merge($this->video(), ['id' => 'vbbbbbbbb'])]]);
        $this->get(route('programa.recursos.index'))->assertOk()
            ->assertSee('2 videos')
            ->assertSee('data-videos="2"', false)
            ->assertSee('data-partitura="0"', false)
            ->assertSee('data-audio="0"', false);
    }

    public function test_la_grabacion_cuenta_como_recurso_de_audio(): void
    {
        $score = $this->conGolpes();
        $score['audio_ref'] = ['path' => 'partituras/audio/1-abcdefghijklmnop.mp3', 'offset_seg' => 0, 'beats' => []];
        $this->toque('con-audio', ['partitura_score' => $score]);

        $this->get(route('programa.recursos.index'))->assertOk()->assertSee('data-audio="1"', false);
    }

    public function test_la_lista_no_expone_datos_de_administracion_ni_rutas_de_archivos(): void
    {
        $this->toque('publicado', [
            'videos_multipista' => [$this->video()],
            'partitura_score' => $this->conGolpes(),
            'partitura_ediciones' => [['nombre' => 'Persona Interna', 'at' => now()->toIso8601String(), 'ip' => '10.0.0.7']],
        ], ['partitura_borrador' => json_encode(['secreto' => 'borrador-interno']), 'partitura_borrador_autor' => 'Persona Interna']);

        $html = $this->get(route('programa.recursos.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Persona Interna', $html);
        $this->assertStringNotContainsString('10.0.0.7', $html);
        $this->assertStringNotContainsString('borrador-interno', $html);
        $this->assertStringNotContainsString('partituras/videos/', $html, 'los archivos no se listan: solo conteos');
        $this->assertStringNotContainsString('/video/', $html);
    }

    public function test_sin_toques_muestra_el_mensaje_vacio(): void
    {
        $this->toque('sin-nada', []);
        $this->get(route('programa.recursos.index'))->assertOk()->assertSee('Todavía no hay toques con recursos publicados.');
    }

    public function test_la_pagina_de_un_toque_solo_muestra_toques_publicados(): void
    {
        $this->toque('oculto', ['videos_multipista' => [$this->video()]], ['publicado' => false]);
        $this->toque('fuera', ['videos_multipista' => [$this->video()]], ['en_programa' => false]);

        $this->get('/programa/recursos/oculto')->assertNotFound();
        $this->get('/programa/recursos/fuera')->assertNotFound();
    }

    public function test_visitante_sin_cuenta_ve_la_pagina_sin_archivos(): void
    {
        $this->toque('con-videos', ['videos_multipista' => [$this->video()]]);

        $html = $this->get('/programa/recursos/con-videos')->assertOk()->assertSee('Entrar')->getContent();
        $this->assertStringNotContainsString('data-videos-multipista', $html);
        $this->assertStringNotContainsString('/video/', $html);
        $this->assertStringNotContainsString('partituras/videos/', $html);
    }

    public function test_alumno_de_otro_año_no_recibe_las_urls(): void
    {
        // Toque de 2° año; el alumno cursa 1°: el material se abre cuando llegue a 2°.
        $this->toque('con-videos', ['videos_multipista' => [$this->video()]]);
        $sede = $this->sede('Sede recursos');
        $bloque = $this->bloque($sede, 'Bloque 1', 1);
        $persona = $this->persona('Alumno de primero');
        $this->inscribirAlumno($persona, $bloque);
        $this->actingAs($this->usuario('Alumno de primero', $persona));

        $html = $this->get('/programa/recursos/con-videos')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-videos-multipista', $html);
        $this->assertStringNotContainsString('/video/', $html);
    }

    public function test_quien_tiene_acceso_ve_el_reproductor(): void
    {
        $this->toque('con-videos', ['videos_multipista' => [$this->video()]]);
        $this->actingAs($this->admin());

        $this->get('/programa/recursos/con-videos')->assertOk()
            ->assertSee('data-videos-multipista', false)
            ->assertSee('Ejecutar por separado');
    }

    public function test_la_pagina_no_expone_datos_de_administracion(): void
    {
        $this->toque('con-datos', [
            'videos_multipista' => [$this->video()],
            'partitura_ediciones' => [['nombre' => 'Persona Interna', 'at' => now()->toIso8601String(), 'ip' => '10.0.0.7']],
        ], ['partitura_borrador' => json_encode(['x' => 'borrador-interno'])]);

        $html = $this->get('/programa/recursos/con-datos')->assertOk()->getContent();
        $this->assertStringNotContainsString('Persona Interna', $html);
        $this->assertStringNotContainsString('10.0.0.7', $html);
        $this->assertStringNotContainsString('borrador-interno', $html);
    }
}
