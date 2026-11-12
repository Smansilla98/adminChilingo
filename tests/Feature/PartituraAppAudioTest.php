<?php

namespace Tests\Feature;

use App\Models\ProgramaRitmo;
use App\Support\PartituraAudio;
use App\Support\PartituraScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

/** App: subida de la grabación del toque (queda en la partitura publicada) y estado del transcriptor. */
class PartituraAppAudioTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function audio(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('ensayo.mp3', "ID3\x03\x00\x00\x00\x00\x00\x00".str_repeat("\xFF\xFB\x90\x00", 300));
    }

    private function toque(): ProgramaRitmo
    {
        return ProgramaRitmo::query()->create([
            'slug' => 'zz-app-audio', 'año' => 1, 'orden' => 98, 'nombre' => 'App audio', 'publicado' => true,
            'medios' => ['partitura_score' => PartituraScore::vacia('App audio')],
        ]);
    }

    public function test_subir_audio_lo_deja_en_la_partitura_sin_crear_version(): void
    {
        Storage::fake(PartituraAudio::disco());
        config(['transcriptor.url' => null]);
        $toque = $this->toque();
        Sanctum::actingAs($this->admin());

        $r = $this->post('/api/v1/partituras/zz-app-audio/audio', ['audio' => $this->audio()], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('transcripcion', null);
        $path = $r->json('audio_ref.path');

        $score = $toque->fresh()->mediosNormalizados()['partitura_score'];
        $this->assertSame($path, $score['audio_ref']['path']);
        $this->assertSame(0, \App\Models\PartituraVersion::query()->count(), 'la música no cambia: no hay versión nueva');
        $this->assertTrue(PartituraScore::tieneGolpes($score) === false);
    }

    public function test_transcribir_sin_servicio_explica_con_503(): void
    {
        Storage::fake(PartituraAudio::disco());
        config(['transcriptor.url' => null, 'transcriptor.secreto' => null]);
        $this->toque();
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/partituras/zz-app-audio/transcripciones', ['path' => 'partituras/audio/1-abcdefghijklmnop.mp3'])
            ->assertStatus(503)->assertJsonStructure(['message']);
        $this->getJson('/api/v1/partituras-transcriptor')->assertOk()->assertJsonPath('transcripcion.habilitada', false);
    }

    public function test_ajustar_inicio_guarda_pulsos_sin_crear_version(): void
    {
        Storage::fake(PartituraAudio::disco());
        $toque = $this->toque();
        Sanctum::actingAs($this->admin());
        $this->post('/api/v1/partituras/zz-app-audio/audio', ['audio' => $this->audio()], ['Accept' => 'application/json'])->assertCreated();

        $this->putJson('/api/v1/partituras/zz-app-audio/audio/inicio', ['offset_seg' => 1.25, 'beats' => [1.25, 1.8, 2.3, 2.9]])
            ->assertOk()->assertJsonPath('audio_ref.offset_seg', 1.25);

        $ref = $toque->fresh()->mediosNormalizados()['partitura_score']['audio_ref'];
        $this->assertSame([1.25, 1.8, 2.3, 2.9], $ref['beats']);
        $this->assertSame(0, \App\Models\PartituraVersion::query()->count());
    }

    public function test_ajustar_inicio_sin_grabacion_es_422(): void
    {
        $this->toque();
        Sanctum::actingAs($this->admin());
        $this->putJson('/api/v1/partituras/zz-app-audio/audio/inicio', ['offset_seg' => 1])->assertStatus(422);
    }
}
