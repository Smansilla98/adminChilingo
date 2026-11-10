<?php

namespace Tests\Feature;

use App\Models\ProgramaRitmo;
use App\Services\PartituraExportador;
use App\Support\PartituraScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

/** MIDI y MusicXML generados en el servidor (para la app y para descargar). */
class PartituraExportadorTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function score(): array
    {
        return (array) PartituraScore::normalizar([
            'title' => 'Ochosi <& prueba>',
            'tempo' => 90,
            'timeSignature' => ['num' => 4, 'den' => 4],
            'instruments' => [['id' => 'surdo_grave'], ['id' => 'timbal'], ['id' => 'todos']],
            'sections' => [[
                'name' => 'Toque', 'repeatX' => 2,
                'measures' => [[
                    'texto' => 'Revisar: algo',
                    'voces' => [
                        'surdo_grave' => [['dur' => 'q', 'stroke' => 'nota'], ['dur' => 'q', 'stroke' => 'tapado', 'dyn' => 'p']],
                        'timbal' => [['dur' => '8', 'stroke' => 'slap'], ['dur' => '8', 'stroke' => 'abierto'],
                            ['dur' => '8', 'tuplet' => ['num' => 3, 'den' => 2]], ['dur' => '8', 'tuplet' => ['num' => 3, 'den' => 2]], ['dur' => '8', 'tuplet' => ['num' => 3, 'den' => 2]]],
                        'todos' => [['dur' => 'h', 'rest' => true], ['dur' => 'q', 'stroke' => 'acentuado']],
                    ],
                ]],
            ]],
        ]);
    }

    public function test_midi_tiene_cabecera_y_notas_en_canal_10(): void
    {
        $bytes = (new PartituraExportador)->midi($this->score());

        $this->assertSame('MThd', substr($bytes, 0, 4));
        $this->assertSame([1 => 6], unpack('N', substr($bytes, 4, 4)));
        $this->assertSame([1 => 48], unpack('n', substr($bytes, 12, 2)), 'resolución TPQ 48');
        $this->assertSame('MTrk', substr($bytes, 14, 4));
        $largo = unpack('N', substr($bytes, 18, 4))[1];
        $this->assertSame(strlen($bytes) - 22, $largo);
        $this->assertStringEndsWith("\xFF\x2F\x00", $bytes);
        // Surdo grave abierto (87), tapado (86), timbal slap (65) en canal 10 (0x99).
        $this->assertStringContainsString("\x99".chr(87), $bytes);
        $this->assertStringContainsString("\x99".chr(86), $bytes);
        $this->assertStringContainsString("\x99".chr(65), $bytes);
        // repeatX 2: el golpe de surdo grave abierto suena dos veces.
        $this->assertSame(2, substr_count($bytes, "\x99".chr(87)) - 2, 'la voz Todos también dispara surdo grave (x2)');
    }

    public function test_musicxml_bien_formado(): void
    {
        $xml = (new PartituraExportador)->musicXml($this->score());
        $doc = new \DOMDocument;
        $this->assertTrue($doc->loadXML($xml, LIBXML_NONET), 'XML bien formado');
        $this->assertSame('score-partwise', $doc->documentElement->nodeName);
        $this->assertSame(3, $doc->getElementsByTagName('part')->length);
        $this->assertStringContainsString('Ochosi &lt;&amp; prueba&gt;', $xml);
        $this->assertStringContainsString('<repeat direction="backward" times="2"/>', $xml);
        $this->assertStringContainsString('<actual-notes>3</actual-notes>', $xml);
        // Cada compás de cada parte suma 192 ticks.
        foreach ($doc->getElementsByTagName('measure') as $m) {
            $total = 0;
            foreach ($m->getElementsByTagName('duration') as $d) {
                $total += (int) $d->textContent;
            }
            $this->assertSame(192, $total);
        }
    }

    public function test_endpoints_web_y_api(): void
    {
        $toque = ProgramaRitmo::query()->create([
            'slug' => 'zz-exportar', 'año' => 1, 'orden' => 99, 'nombre' => 'Exportar', 'publicado' => true,
            'medios' => ['partitura_score' => $this->score()],
        ]);
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('programa.toque.exportar', [$toque, 'midi']))
            ->assertOk()->assertHeader('Content-Type', 'audio/midi');
        Sanctum::actingAs($admin);
        $r = $this->get('/api/v1/partituras/zz-exportar/exportar/musicxml')->assertOk();
        $this->assertStringContainsString('attachment; filename="exportar.musicxml"', (string) $r->headers->get('Content-Disposition'));
        $this->get('/api/v1/partituras/zz-exportar/exportar/pdf')->assertNotFound();
    }
}
