<?php

namespace Tests\Feature;

use App\Domain\Partituras\DatasetCorrecciones;
use App\Models\PartituraTranscripcion;
use App\Models\ProgramaRitmo;
use App\Support\PartituraScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Dataset de correcciones humanas (borrador automático vs versión publicada). */
class PartituraDatasetTest extends TestCase
{
    use RefreshDatabase;

    private function partitura(string $stroke): array
    {
        return PartituraScore::normalizar([
            'title' => 'T', 'tempo' => 90, 'timeSignature' => ['num' => 4, 'den' => 4],
            'instruments' => [['id' => 'surdo_grave']],
            'sections' => [['name' => 'Toque', 'measures' => [['voces' => ['surdo_grave' => [
                ['dur' => 'q', 'stroke' => $stroke], ['dur' => 'q', 'stroke' => 'nota'], ['dur' => 'h', 'rest' => true],
            ]]]]]],
        ]);
    }

    private function transcripcion(string $slug, string $publicado, string $borrador): void
    {
        $toque = ProgramaRitmo::query()->create([
            'slug' => $slug, 'año' => 1, 'orden' => 1, 'nombre' => $slug, 'publicado' => true,
            'medios' => ['partitura_score' => $this->partitura($publicado)],
        ]);
        PartituraTranscripcion::query()->create([
            'programa_ritmo_id' => $toque->id, 'estado' => PartituraTranscripcion::LISTO, 'progreso' => 100,
            'audio_path' => 'partituras/audio/'.$toque->id.'-abcdefghijklmnop.mp3',
            'resultado' => ['tempo' => 90, 'golpes' => [['t' => 0.0, 'inst' => 'surdo_grave', 'golpe' => 'nota', 'vel' => 90, 'conf' => 0.7]]],
            'borrador' => $this->partitura($borrador),
        ]);
    }

    public function test_solo_exporta_los_toques_que_la_escuela_corrigio(): void
    {
        $this->transcripcion('corregido', 'acentuado', 'nota');
        $this->transcripcion('igual', 'nota', 'nota');

        $registros = iterator_to_array((new DatasetCorrecciones)->registros(), false);
        $this->assertCount(1, $registros);
        $this->assertSame('corregido', $registros[0]['toque']);
        $this->assertSame('acentuado', $registros[0]['golpes_publicados'][0]['golpe']);
        $this->assertSame('nota', $registros[0]['golpes_borrador'][0]['golpe']);
        $this->assertSame(1, $registros[0]['coinciden']);
    }

    public function test_comando_escribe_jsonl(): void
    {
        $this->transcripcion('corregido', 'acentuado', 'nota');
        $ruta = sys_get_temp_dir().'/dataset-'.uniqid().'.jsonl';

        $this->artisan('partituras:dataset-correcciones', ['salida' => $ruta])->assertSuccessful();

        $lineas = file($ruta, FILE_IGNORE_NEW_LINES);
        $this->assertCount(1, $lineas);
        $this->assertSame('corregido', json_decode($lineas[0], true)['toque']);
        @unlink($ruta);
    }
}
