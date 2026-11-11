<?php

namespace Tests\Feature;

use App\Domain\Partituras\RevisionPartituras;
use App\Domain\Partituras\TranscriptorClient;
use App\Models\PartituraTranscripcion;
use App\Models\ProgramaRitmo;
use App\Support\PartituraAudio;
use App\Support\PartituraDesdeGolpes;
use App\Support\PartituraScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

/** Grabación de referencia, cuantización de golpes y transcripción con el servicio simulado. */
class PartituraTranscripcionTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    /** Pulsos que aceleran levemente: de 90 a ~100 BPM en 8 compases. */
    private function pulsosAcelerando(int $n, float $inicio = 1.0): array
    {
        $beats = [];
        $t = $inicio;
        for ($i = 0; $i < $n; $i++) {
            $beats[] = round($t, 4);
            $t += 60 / (90 + $i * 0.35);
        }

        return $beats;
    }

    /** Golpe a una fracción `f` entre el pulso i y el siguiente, con un desvío humano. */
    private function en(array $beats, int $i, float $f, float $desvio = 0.0): float
    {
        return $beats[$i] + $f * ($beats[$i + 1] - $beats[$i]) + $desvio;
    }

    public function test_cuantiza_con_aceleracion_leve_tresillos_y_repeticiones(): void
    {
        $beats = $this->pulsosAcelerando(34);
        $golpes = [];
        // Anacrusa antes del primer tiempo: se descarta.
        $golpes[] = ['t' => 0.6, 'inst' => 'repique', 'golpe' => 'nota', 'vel' => 90, 'conf' => 0.9];
        for ($c = 0; $c < 8; $c++) {
            $b = $c * 4;
            // Surdo grave en 1 y 3; redoblante en semicorcheas 2 y 4 del tiempo 2 (con ±12 ms).
            $golpes[] = ['t' => $this->en($beats, $b, 0, 0.012), 'inst' => 'surdo_grave', 'golpe' => 'nota', 'vel' => 110, 'conf' => 0.95];
            $golpes[] = ['t' => $this->en($beats, $b + 2, 0, -0.01), 'inst' => 'surdo_grave', 'golpe' => 'tapado', 'vel' => 80, 'conf' => 0.9];
            $golpes[] = ['t' => $this->en($beats, $b + 1, 0.25, 0.008), 'inst' => 'redoblante', 'golpe' => 'nota', 'vel' => 70, 'conf' => 0.8];
            $golpes[] = ['t' => $this->en($beats, $b + 1, 0.75, -0.009), 'inst' => 'redoblante', 'golpe' => 'acentuado', 'vel' => 100, 'conf' => 0.8];
            if ($c >= 6) {
                // Últimos dos compases: tresillo de repique en el tiempo 4, y un golpe dudoso.
                foreach ([0, 1 / 3, 2 / 3] as $k => $f) {
                    $golpes[] = ['t' => $this->en($beats, $b + 3, $f, 0.005), 'inst' => 'repique', 'golpe' => 'nota', 'vel' => 90, 'conf' => $k === 1 && $c === 7 ? 0.2 : 0.9];
                }
            }
        }

        $r = PartituraDesdeGolpes::convertir([
            'tempo' => 94, 'ts' => ['num' => 4, 'den' => 4],
            'beats' => $beats, 'downbeats' => [$beats[0], $beats[4]],
            'golpes' => $golpes,
        ], 'Prueba', PartituraScore::normalizarAudioRef(['path' => 'partituras/audio/1-abcdefghijklmnop.mp3']));

        $s = $r['score'];
        $this->assertNotNull($s);
        $this->assertSame(8, $r['compases']);
        $this->assertSame(1, $r['dudosos']);
        $this->assertSame('Transcripción automática', $s['autor']);
        $this->assertSame(['surdo_grave', 'redoblante', 'repique'], array_column($s['instruments'], 'id'));
        // 6 compases iguales → una parte ×6; luego los dos con tresillo (el último marcado).
        $this->assertSame(6, $s['sections'][0]['repeatX']);
        $this->assertCount(1, $s['sections'][0]['measures']);
        $compas = $s['sections'][0]['measures'][0];
        $redo = array_values(array_filter($compas['voces']['redoblante'], fn ($n) => ! $n['rest']));
        $this->assertSame(['nota', 'acentuado'], array_column($redo, 'stroke'));
        $this->assertSame(['8', '16'], array_column($redo, 'dur'));
        // Posiciones en ticks: redoblante en 60 y 84 (semicorcheas 2 y 4 del tiempo 2).
        $this->assertSame([60, 84], $this->ticksDeGolpes($compas['voces']['redoblante']));
        $this->assertSame([0, 96], $this->ticksDeGolpes($compas['voces']['surdo_grave']));

        $ultima = end($s['sections']);
        $final = end($ultima['measures']);
        $repique = $final['voces']['repique'];
        $this->assertSame(['num' => 3, 'den' => 2], array_intersect_key($repique[count($repique) - 1]['tuplet'], ['num' => 1, 'den' => 1]));
        $this->assertSame([144, 160, 176], $this->ticksDeGolpes($repique));
        $this->assertStringStartsWith('Revisar:', (string) $final['texto']);
        $this->assertNotEmpty(RevisionPartituras::marcas($s));
        // La grabación queda sincronizada por pulsos desde el primer tiempo.
        $this->assertSame(round($beats[0], 3), $s['audio_ref']['offset_seg']);
        $this->assertGreaterThanOrEqual(32, count($s['audio_ref']['beats']));
    }

    private function ticksDeGolpes(array $voz): array
    {
        $out = [];
        $t = 0;
        foreach ($voz as $n) {
            if (! $n['rest']) {
                $out[] = $t;
            }
            $t += PartituraScore::ticksDeNota($n);
        }

        return $out;
    }

    public function test_sin_golpes_no_hay_partitura(): void
    {
        $this->assertNull(PartituraDesdeGolpes::convertir(['golpes' => [['t' => 1, 'inst' => 'guitarra']]], 'X')['score']);
    }

    public function test_audio_ref_solo_acepta_rutas_propias(): void
    {
        $this->assertNull(PartituraScore::normalizarAudioRef(['path' => 'comprobantes/pago.pdf']));
        $this->assertNull(PartituraScore::normalizarAudioRef(['path' => 'partituras/audio/../x.mp3']));
        $this->assertNull(PartituraScore::normalizarAudioRef(['url' => 'http://inseguro.com/a.mp3']));
        $ref = PartituraScore::normalizarAudioRef(['path' => 'partituras/audio/3-abcdefghijklmnop.mp3', 'beats' => [2, 1, 3, 'x'], 'tempo_origen' => 500, 'offset_seg' => -2]);
        $this->assertSame([2.0, 3.0], $ref['beats']);
        $this->assertNull($ref['tempo_origen']);
        $this->assertSame(0.0, (float) $ref['offset_seg']);
    }

    private function toque(): ProgramaRitmo
    {
        return ProgramaRitmo::query()->create([
            'slug' => 'zz-transcribir', 'año' => 1, 'orden' => 99, 'nombre' => 'Transcribir', 'publicado' => true,
            'medios' => ['partitura_score' => PartituraScore::vacia('Transcribir')],
        ]);
    }

    public function test_callback_con_firma_invalida_es_401(): void
    {
        config(['transcriptor.secreto' => 'secreto-de-prueba']);
        $this->postJson('/api/transcriptor/callback', ['job_id' => 1, 'estado' => 'listo'])->assertStatus(401);
        $this->call('POST', '/api/transcriptor/callback', [], [], [], ['HTTP_X_TRANSCRIPTOR_FIRMA' => 'sha256=nada', 'CONTENT_TYPE' => 'application/json'], '{"job_id":1}')
            ->assertStatus(401);
        config(['transcriptor.secreto' => '']);
        $cuerpo = '{"job_id":1}';
        $this->call('POST', '/api/transcriptor/callback', [], [], [], ['HTTP_X_TRANSCRIPTOR_FIRMA' => TranscriptorClient::firmar($cuerpo), 'CONTENT_TYPE' => 'application/json'], $cuerpo)
            ->assertStatus(401);
    }

    public function test_apagado_sin_url_explica_y_no_crea_pedido(): void
    {
        config(['transcriptor.url' => null]);
        Storage::fake(PartituraAudio::disco());
        $toque = $this->toque();
        Sanctum::actingAs($this->admin());
        $this->getJson('/api/v1/partituras-transcriptor')->assertOk()->assertJsonPath('transcripcion.habilitada', false);
        $this->postJson('/api/v1/partituras/zz-transcribir/transcripciones', ['path' => 'partituras/audio/'.$toque->id.'-abcdefghijklmnop.mp3'])
            ->assertStatus(503)->assertJsonFragment(['message' => app(TranscriptorClient::class)->motivoApagado()]);
        $this->assertSame(0, PartituraTranscripcion::query()->count());
    }

    public function test_flujo_completo_con_servicio_simulado(): void
    {
        config(['transcriptor.url' => 'https://transcriptor.test', 'transcriptor.secreto' => 'secreto-de-prueba']);
        Storage::fake(PartituraAudio::disco());
        $enviado = null;
        Http::fake(function ($request) use (&$enviado) {
            $enviado = $request;

            return Http::response(['ok' => true], 202);
        });
        $toque = $this->toque();
        $admin = $this->admin();
        $this->actingAs($admin);

        // 1. Subir la grabación desde el editor web.
        $audio = UploadedFile::fake()->createWithContent('ensayo.mp3', "ID3\x03\x00\x00\x00\x00\x00\x00".str_repeat("\xFF\xFB\x90\x00", 300));
        $subida = $this->post(route('programa.toque.editor.audio', $toque), ['audio' => $audio, 'editor_nombre' => 'Lu'], ['Accept' => 'application/json'])
            ->assertOk()->json();
        $path = $subida['audio_ref']['path'];
        $this->assertStringStartsWith('partituras/audio/'.$toque->id.'-', $path);
        Storage::disk(PartituraAudio::disco())->assertExists($path);
        $this->get($subida['url'])->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

        // Un archivo que no es audio se rechaza.
        $tmp = tempnam(sys_get_temp_dir(), 'falso');
        file_put_contents($tmp, '<!DOCTYPE html><html><body><script>alert(1)</script></body></html>');
        $falso = new UploadedFile($tmp, 'falso.mp3', null, null, true);
        $this->post(route('programa.toque.editor.audio', $toque), ['audio' => $falso, 'editor_nombre' => 'Lu'], ['Accept' => 'application/json'])->assertStatus(422);

        // 2. Pedir la transcripción: se manda firmada al servicio.
        $r = $this->postJson(route('programa.toque.editor.transcribir', $toque), ['path' => $path, 'editor_nombre' => 'Lu'])->assertStatus(202);
        $id = $r->json('data.id');
        $this->assertSame('procesando', $r->json('data.estado'));
        $this->assertNotNull($enviado);
        $this->assertTrue(TranscriptorClient::firmaValida($enviado->body(), $enviado->header(TranscriptorClient::CABECERA_FIRMA)[0] ?? null));
        $pedido = json_decode($enviado->body(), true);
        $this->assertSame((string) $id, $pedido['job_id']);

        // El servicio baja el audio con el enlace firmado; sin firma, no.
        $this->get($pedido['audio_url'])->assertOk();
        $this->get(route('transcriptor.audio', $id))->assertStatus(403);

        // 3. Avisa el resultado firmado: queda un borrador, no se publica.
        $beats = $this->pulsosAcelerando(9);
        $golpes = [];
        foreach ([0, 4] as $b) {
            $golpes[] = ['t' => $beats[$b], 'inst' => 'surdo_grave', 'golpe' => 'nota', 'vel' => 110, 'conf' => 0.9];
            $golpes[] = ['t' => $beats[$b + 2], 'inst' => 'surdo_grave', 'golpe' => 'nota', 'vel' => 110, 'conf' => 0.3];
        }
        $cuerpo = json_encode(['job_id' => $id, 'estado' => 'listo', 'resultado' => ['tempo' => 91, 'ts' => ['num' => 4, 'den' => 4], 'beats' => $beats, 'downbeats' => [$beats[0]], 'golpes' => $golpes]]);
        $this->call('POST', '/api/transcriptor/callback', [], [], [], ['HTTP_X_TRANSCRIPTOR_FIRMA' => TranscriptorClient::firmar($cuerpo), 'CONTENT_TYPE' => 'application/json'], $cuerpo)
            ->assertOk()->assertJsonPath('estado', 'listo');

        $toque->refresh();
        $this->assertSame('Transcripción automática', $toque->partitura_borrador_autor);
        $borrador = json_decode((string) $toque->partitura_borrador, true);
        $this->assertSame($path, $borrador['audio_ref']['path']);
        $this->assertFalse(PartituraScore::tieneGolpes($toque->mediosNormalizados()['partitura_score']), 'lo publicado no cambia');
        $this->assertNotEmpty(RevisionPartituras::marcas($borrador), 'los golpes dudosos quedan para revisar');

        $this->getJson(route('programa.toque.editor.transcripcion', [$toque, $id]))->assertOk()
            ->assertJsonPath('data.estado', 'listo')->assertJsonPath('data.progreso', 100);
        // El enlace ya no sirve una vez terminado.
        $this->get($pedido['audio_url'])->assertStatus(410);
    }
}
