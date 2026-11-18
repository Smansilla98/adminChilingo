<?php

namespace Tests\Feature;

use App\Support\ProgramaRitmoMedios;
use App\Support\VideosMultipista;
use Tests\TestCase;

/** Modelo de los videos multipista (medios.videos_multipista), separado de los links. */
class VideosMultipistaModeloTest extends TestCase
{
    private function video(array $extra = []): array
    {
        return array_merge([
            'id' => 'vabcdefgh',
            'instrumento' => 'surdo_grave',
            'titulo' => 'Surdo grave',
            'archivo' => 'partituras/videos/7-abcdefghijklmnop.mp4',
            'offset_seg' => 0,
            'orden' => 1,
        ], $extra);
    }

    public function test_descarta_archivos_fuera_de_la_carpeta_o_con_extension_no_permitida(): void
    {
        $out = VideosMultipista::normalizar([
            $this->video(),
            $this->video(['id' => 'vbbbbbbbb', 'archivo' => '../../comprobantes/x.mp4']),
            $this->video(['id' => 'vcccccccc', 'archivo' => 'partituras/videos/7-abcdefghijklmnop.exe']),
            $this->video(['id' => 'vdddddddd', 'archivo' => 'partituras/videos/7-abcdefghijklmnop.html']),
        ]);
        $this->assertCount(1, $out);
        $this->assertSame('vabcdefgh', $out[0]['id']);
    }

    public function test_instrumento_desconocido_pasa_a_voz_y_todos_no_es_un_instrumento(): void
    {
        $out = VideosMultipista::normalizar([
            $this->video(['id' => 'v11111111', 'instrumento' => 'guitarra', 'titulo' => '']),
            $this->video(['id' => 'v22222222', 'instrumento' => 'todos']),
            $this->video(['id' => 'v33333333', 'instrumento' => 'voz']),
        ]);
        $this->assertSame('voz', $out[0]['instrumento']);
        $this->assertSame('Voz', $out[0]['titulo'], 'sin título se usa el nombre del instrumento');
        $this->assertSame('voz', $out[1]['instrumento']);
        $this->assertSame('voz', $out[2]['instrumento']);
    }

    public function test_offset_se_acota_y_se_redondea(): void
    {
        $out = VideosMultipista::normalizar([
            $this->video(['id' => 'v11111111', 'offset_seg' => 42.12345]),
            $this->video(['id' => 'v22222222', 'offset_seg' => -99]),
            $this->video(['id' => 'v33333333', 'offset_seg' => 'x']),
        ]);
        $this->assertSame(10.0, $out[0]['offset_seg']);
        $this->assertSame(-10.0, $out[1]['offset_seg']);
        $this->assertSame(0.0, $out[2]['offset_seg']);
    }

    public function test_maximo_seis_y_orden_renumerado(): void
    {
        $raw = [];
        for ($i = 1; $i <= 8; $i++) {
            $raw[] = $this->video(['id' => 'v'.str_repeat((string) $i, 8), 'orden' => 9 - $i]);
        }
        $out = VideosMultipista::normalizar($raw);
        $this->assertCount(VideosMultipista::MAX, $out);
        $this->assertSame([1, 2, 3, 4, 5, 6], array_column($out, 'orden'));
        $this->assertSame('v88888888', $out[0]['id'], 'se ordena por el orden pedido');
    }

    public function test_ids_repetidos_o_invalidos_se_regeneran(): void
    {
        $out = VideosMultipista::normalizar([
            $this->video(['id' => 'vabcdefgh']),
            $this->video(['id' => 'vabcdefgh', 'archivo' => 'partituras/videos/7-zyxwvutsrqponmlk.webm']),
            $this->video(['id' => 'NO VALIDO', 'archivo' => 'partituras/videos/7-ponmlkjihgfedcba.mov']),
        ]);
        $ids = array_column($out, 'id');
        $this->assertSame(3, count(array_unique($ids)));
        $this->assertMatchesRegularExpression('/^v[a-z0-9]{8}$/', $ids[2]);
    }

    public function test_medios_del_toque_guarda_la_lista_y_no_toca_los_links(): void
    {
        $medios = ProgramaRitmoMedios::normalizar([
            'videos_base' => ['surdo_grave' => ['url' => 'https://youtu.be/abc']],
            'videos_multipista' => [$this->video()],
        ]);
        $this->assertCount(1, $medios['videos_multipista']);
        $this->assertSame('https://youtu.be/abc', $medios['videos_base']['surdo_grave']['url']);
        $this->assertSame([], ProgramaRitmoMedios::normalizar([])['videos_multipista']);
    }
}
