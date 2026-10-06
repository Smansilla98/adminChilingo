<?php

namespace Tests\Feature;

use App\Models\PartituraVersion;
use App\Models\ProgramaRitmo;
use App\Support\PartituraScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

/** Editor de ritmos: modelo v5 compatible, borrador autoguardado y versiones publicadas. */
class PartituraHistorialTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function score(array $notas, array $extra = []): array
    {
        return array_replace_recursive([
            'version' => 4,
            'title' => 'Ochosi en Murga',
            'tempo' => 88,
            'timeSignature' => ['num' => 4, 'den' => 4],
            'instruments' => [['id' => 'surdo_grave'], ['id' => 'repique']],
            'sections' => [['name' => 'Toque', 'measures' => [['voces' => ['surdo_grave' => $notas]]]]],
        ], $extra);
    }

    private function toque(): ProgramaRitmo
    {
        return ProgramaRitmo::query()->create([
            'slug' => 'zz-prueba-historial', 'año' => 9, 'orden' => 99, 'nombre' => 'Prueba de historial', 'publicado' => true,
            'medios' => ['partitura_score' => PartituraScore::normalizar($this->score([['dur' => 'q', 'stroke' => 'nota']]))],
        ]);
    }

    public function test_v5_conserva_campos_nuevos_y_lee_v4(): void
    {
        $s = PartituraScore::normalizar($this->score([
            ['dur' => '16', 'stroke' => 'fantasma', 'vel' => 300],
            ['dur' => '16', 'stroke' => 'acentuado', 'vel' => 90],
            ['dur' => '8', 'stroke' => 'nota'],
        ], [
            'instruments' => [['id' => 'surdo_grave', 'pan' => -3, 'pitch' => 5], ['id' => 'repique']],
            'sections' => [['measures' => [['sena' => ['texto' => 'Entrada de repique', 'tipo' => 'entrada', 'instrumento' => 'repique']]]]],
        ]));

        $this->assertSame(5, $s['version']);
        $voz = $s['sections'][0]['measures'][0]['voces']['surdo_grave'];
        $this->assertSame('fantasma', $voz[0]['stroke']);
        $this->assertSame(127, $voz[0]['vel'], 'vel se acota a 1..127');
        $this->assertSame(90, $voz[1]['vel']);
        $this->assertNull($voz[2]['vel']);
        $this->assertSame(-1.0, (float) $s['instruments'][0]['pan']);
        $this->assertSame(5, $s['instruments'][0]['pitch']);
        $this->assertSame(0, $s['instruments'][1]['pitch'], 'v4 sin pitch recibe el valor por defecto');
        $this->assertSame(['texto' => 'Entrada de repique', 'tipo' => 'entrada', 'instrumento' => 'repique'], $s['sections'][0]['measures'][0]['sena']);
        $this->assertSame(192, array_sum(array_map(fn ($n) => PartituraScore::ticksDeNota($n), $voz)), 'el compás se completa con silencios');
    }

    public function test_publicar_crea_versiones_y_borrador_se_recupera(): void
    {
        $toque = $this->toque();
        $url = route('programa.toque.editor.guardar', $toque);

        // Autoguardado: no publica, queda como borrador pendiente.
        $this->postJson(route('programa.toque.editor.borrador', $toque), ['score' => $this->score([['dur' => 'h', 'stroke' => 'acentuado']]), 'editor_nombre' => 'Lu'])
            ->assertOk()->assertJsonStructure(['at']);
        $this->assertSame('nota', $toque->fresh()->mediosNormalizados()['partitura_score']['sections'][0]['measures'][0]['voces']['surdo_grave'][0]['stroke']);
        $this->get(route('programa.toque.editor', $toque))->assertOk()->assertSee('data-borrador=', false);

        // Publicar: versión 1, borrador descartado.
        $this->postJson($url, ['score' => $this->score([['dur' => 'h', 'stroke' => 'acentuado']]), 'editor_nombre' => 'Lucía', 'nota' => 'Primera'])
            ->assertOk()->assertJsonPath('version', 1);
        $this->assertNull($toque->fresh()->partitura_borrador);

        // Misma música: no duplica. Música distinta: versión 2.
        $this->postJson($url, ['score' => $this->score([['dur' => 'h', 'stroke' => 'acentuado']]), 'editor_nombre' => 'Lucía'])->assertJsonPath('version', 1);
        $this->postJson($url, ['score' => $this->score([['dur' => 'w', 'stroke' => 'tapado']]), 'editor_nombre' => 'Pablo'])->assertJsonPath('version', 2);

        $this->getJson(route('programa.toque.editor.versiones', $toque))->assertOk()
            ->assertJsonPath('data.0.numero', 2)->assertJsonPath('data.0.autor', 'Pablo')->assertJsonPath('data.1.nota', 'Primera');
        $this->getJson(route('programa.toque.editor.version', [$toque, 1]))->assertOk()
            ->assertJsonPath('data.score.sections.0.measures.0.voces.surdo_grave.0.stroke', 'acentuado');
        $this->getJson(route('programa.toque.editor.version', [$toque, 9]))->assertNotFound();
    }

    public function test_sin_edicion_publica_hace_falta_permiso(): void
    {
        config(['chilinga.edicion_publica_programa' => false]);
        $toque = $this->toque();
        $this->postJson(route('programa.toque.editor.borrador', $toque), ['score' => $this->score([]), 'editor_nombre' => 'Lu'])->assertForbidden();

        $this->actingAs($this->admin());
        $this->postJson(route('programa.toque.editor.borrador', $toque), ['score' => $this->score([]), 'editor_nombre' => 'Lu'])->assertOk();
        $this->deleteJson(route('programa.toque.editor.borrador.descartar', $toque))->assertOk();
        $this->assertNull($toque->fresh()->partitura_borrador);
    }

    public function test_la_app_publica_por_api_y_ve_versiones(): void
    {
        $toque = $this->toque();
        Sanctum::actingAs($this->admin());

        $this->putJson('/api/v1/partituras/zz-prueba-historial/score', ['score' => $this->score([['dur' => 'q', 'stroke' => 'chapa']])])
            ->assertOk()->assertJsonPath('version', 1);
        $this->getJson('/api/v1/partituras/zz-prueba-historial/versiones')->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame(1, PartituraVersion::query()->where('programa_ritmo_id', $toque->id)->count());
    }
}
