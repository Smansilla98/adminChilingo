<?php

namespace Tests\Feature;

use App\Models\ProgramaRitmo;
use App\Support\PartituraAudio;
use App\Support\PartituraScore;
use App\Support\VideoStream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Escenarios;
use Tests\TestCase;

/** Entrega de los videos multipista con HTTP Range (para saltar en el tiempo). */
class VideosMultipistaStreamTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private const ARCHIVO = 'partituras/videos/%d-abcdefghijklmnop.mp4';

    public function test_interpreta_los_rangos(): void
    {
        $this->assertSame([0, 99], VideoStream::rango('bytes=0-99', 1000));
        $this->assertSame([500, 999], VideoStream::rango('bytes=500-', 1000));
        $this->assertSame([900, 999], VideoStream::rango('bytes=-100', 1000));
        $this->assertSame([0, 999], VideoStream::rango('bytes=0-5000', 1000), 'el fin se acota al tamaño');
        $this->assertNull(VideoStream::rango('', 1000), 'sin cabecera, todo el archivo');
        $this->assertFalse(VideoStream::rango('bytes=2000-', 1000));
        $this->assertFalse(VideoStream::rango('bytes=50-10', 1000));
        $this->assertFalse(VideoStream::rango('items=1-2', 1000));
    }

    public function test_entrega_el_tramo_pedido_con_206_y_los_encabezados_seguros(): void
    {
        Storage::fake(PartituraAudio::disco());
        $toque = $this->toque();
        $ruta = sprintf(self::ARCHIVO, $toque->id);
        Storage::disk(PartituraAudio::disco())->put($ruta, str_repeat('0123456789', 100));
        $nombre = basename($ruta);
        $this->actingAs($this->admin());

        $resp = $this->withHeaders(['Range' => 'bytes=10-19'])
            ->get(route('programa.toque.video', [$toque, $nombre]));

        $resp->assertStatus(206);
        $this->assertSame('bytes 10-19/1000', $resp->headers->get('Content-Range'));
        $this->assertSame('0123456789', $resp->streamedContent());
        $this->assertSame('video/mp4', $resp->headers->get('Content-Type'));
        $this->assertSame('bytes', $resp->headers->get('Accept-Ranges'));
        $this->assertSame('nosniff', $resp->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('sandbox', (string) $resp->headers->get('Content-Security-Policy'));
    }

    public function test_sin_rango_entrega_el_archivo_entero(): void
    {
        Storage::fake(PartituraAudio::disco());
        $toque = $this->toque();
        $ruta = sprintf(self::ARCHIVO, $toque->id);
        Storage::disk(PartituraAudio::disco())->put($ruta, str_repeat('a', 1000));
        $this->actingAs($this->admin());

        $this->get(route('programa.toque.video', [$toque, basename($ruta)]))->assertOk()
            ->assertHeader('Accept-Ranges', 'bytes');
    }

    public function test_un_archivo_que_el_toque_no_lista_no_se_entrega(): void
    {
        Storage::fake(PartituraAudio::disco());
        $toque = $this->toque();
        $ajeno = sprintf(self::ARCHIVO, 999999);
        Storage::disk(PartituraAudio::disco())->put($ajeno, 'x');
        $this->actingAs($this->admin());

        $this->get(route('programa.toque.video', [$toque, basename($ajeno)]))->assertNotFound();
    }

    private function toque(): ProgramaRitmo
    {
        $toque = ProgramaRitmo::query()->create([
            'slug' => 'zz-video-stream', 'año' => 1, 'orden' => 96, 'nombre' => 'Video', 'publicado' => true,
            'medios' => ['partitura_score' => PartituraScore::vacia('Video')],
        ]);
        $id = $toque->id;
        $toque->update(['medios' => [
            'partitura_score' => PartituraScore::vacia('Video'),
            'videos_multipista' => [[
                'id' => 'vabcdefgh', 'instrumento' => 'surdo_grave', 'titulo' => 'Surdo', 'orden' => 1, 'offset_seg' => 0,
                'archivo' => sprintf(self::ARCHIVO, $id),
            ]],
        ]]);

        return $toque->fresh();
    }
}
