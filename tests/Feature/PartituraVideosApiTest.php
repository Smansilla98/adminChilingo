<?php

namespace Tests\Feature;

use App\Models\ProgramaRitmo;
use App\Support\PartituraAudio;
use App\Support\PartituraScore;
use App\Support\VideosMultipista;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

/** API de los videos multipista para la app: lectura de la ficha, entrega con Range y edición. */
class PartituraVideosApiTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function toque(array $videos = []): ProgramaRitmo
    {
        return ProgramaRitmo::query()->create([
            'slug' => 'zz-videos-api', 'año' => 1, 'orden' => 94, 'nombre' => 'Videos API', 'publicado' => true,
            'medios' => ['partitura_score' => PartituraScore::vacia('Videos API'), 'videos_multipista' => $videos],
        ]);
    }

    private function mp4(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('bombo.mp4', "\x00\x00\x00\x18ftypmp42".str_repeat("\x00", 800));
    }

    private function editor(): \App\Models\User
    {
        $persona = $this->persona('Profe API');
        $user = $this->usuario('Profe API', $persona);
        $this->asignarPermiso($persona, 'partituras.edit');

        return $user;
    }

    public function test_la_ficha_de_la_api_lista_los_videos_con_su_url(): void
    {
        Storage::fake(PartituraAudio::disco());
        $toque = $this->toque([[
            'id' => 'vaaaaaaaa', 'instrumento' => 'timbal', 'titulo' => 'Timbal', 'orden' => 1, 'offset_seg' => 0.1,
            'archivo' => 'partituras/videos/'.'1-abcdefghijklmnop.mp4',
        ]]);
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/v1/partituras/zz-videos-api')->assertOk()
            ->assertJsonPath('videos_multipista.0.id', 'vaaaaaaaa')
            ->assertJsonPath('videos_multipista.0.instrumento', 'timbal')
            ->assertJsonPath('videos_multipista.0.url', url('/api/v1/partituras/zz-videos-api/video/1-abcdefghijklmnop.mp4'));
    }

    public function test_entrega_el_video_con_rango_para_la_app(): void
    {
        Storage::fake(PartituraAudio::disco());
        $ruta = 'partituras/videos/'.'1-abcdefghijklmnop.mp4';
        Storage::disk(PartituraAudio::disco())->put($ruta, str_repeat('abcdefghij', 100));
        $this->toque([['id' => 'vaaaaaaaa', 'instrumento' => 'voz', 'titulo' => 'Voz', 'orden' => 1, 'offset_seg' => 0, 'archivo' => $ruta]]);
        Sanctum::actingAs($this->admin());

        $this->withHeaders(['Range' => 'bytes=0-9'])->get('/api/v1/partituras/zz-videos-api/video/1-abcdefghijklmnop.mp4')
            ->assertStatus(206)->assertHeader('Content-Range', 'bytes 0-9/1000');
    }

    public function test_sube_edita_reordena_y_quita_desde_la_api(): void
    {
        Storage::fake(PartituraAudio::disco());
        $toque = $this->toque();
        Sanctum::actingAs($this->editor());

        $id = $this->postJson('/api/v1/partituras/zz-videos-api/videos', ['archivo' => $this->mp4(), 'instrumento' => 'surdo_grave', 'titulo' => 'Surdo'], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('video.instrumento', 'surdo_grave')->json('video.id');

        $this->putJson("/api/v1/partituras/zz-videos-api/videos/{$id}", ['titulo' => 'Surdo grave', 'offset_seg' => -0.25])
            ->assertOk()->assertJsonPath('video.titulo', 'Surdo grave')->assertJsonPath('video.offset_seg', -0.25);

        $this->postJson("/api/v1/partituras/zz-videos-api/videos/{$id}/mover", ['direccion' => 'arriba'])->assertOk();

        $this->deleteJson("/api/v1/partituras/zz-videos-api/videos/{$id}")->assertOk();
        $this->assertSame([], $toque->fresh()->mediosNormalizados()['videos_multipista']);
    }

    public function test_sin_permiso_de_edicion_la_api_rechaza_los_cambios(): void
    {
        Storage::fake(PartituraAudio::disco());
        $this->toque([['id' => 'vaaaaaaaa', 'instrumento' => 'voz', 'titulo' => 'V', 'orden' => 1, 'offset_seg' => 0, 'archivo' => 'partituras/videos/1-abcdefghijklmnop.mp4']]);
        Sanctum::actingAs($this->usuario('Sin permisos API'));

        $this->postJson('/api/v1/partituras/zz-videos-api/videos', ['archivo' => $this->mp4(), 'instrumento' => 'voz'])->assertForbidden();
        $this->putJson('/api/v1/partituras/zz-videos-api/videos/vaaaaaaaa', ['titulo' => 'x'])->assertForbidden();
        $this->deleteJson('/api/v1/partituras/zz-videos-api/videos/vaaaaaaaa')->assertForbidden();
    }

    public function test_un_limite_de_seis_tambien_rige_en_la_api(): void
    {
        Storage::fake(PartituraAudio::disco());
        $this->toque(array_map(fn ($i) => [
            'id' => 'v'.str_repeat((string) $i, 8), 'instrumento' => 'voz', 'titulo' => 'V', 'orden' => $i, 'offset_seg' => 0,
            'archivo' => 'partituras/videos/1-abcdefghijklmnop.mp4',
        ], range(1, VideosMultipista::MAX)));
        Sanctum::actingAs($this->editor());

        $this->postJson('/api/v1/partituras/zz-videos-api/videos', ['archivo' => $this->mp4(), 'instrumento' => 'voz'])->assertStatus(422);
    }
}
