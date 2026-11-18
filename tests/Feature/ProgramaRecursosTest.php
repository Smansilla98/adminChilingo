<?php

namespace Tests\Feature;

use App\Models\ProgramaRitmo;
use App\Support\PartituraScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** «Toques y recursos»: lista pública de toques publicados con recursos, sin datos de administración. */
class ProgramaRecursosTest extends TestCase
{
    use RefreshDatabase;

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
}
