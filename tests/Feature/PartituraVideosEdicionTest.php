<?php

namespace Tests\Feature;

use App\Models\ProgramaRitmo;
use App\Support\PartituraAudio;
use App\Support\PartituraScore;
use App\Support\VideosMultipista;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Escenarios;
use Tests\TestCase;

/** Edición de los videos multipista: subida, permisos, orden, offset y baja. */
class PartituraVideosEdicionTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function toque(array $videos = []): ProgramaRitmo
    {
        return ProgramaRitmo::query()->create([
            'slug' => 'zz-videos-edicion', 'año' => 1, 'orden' => 95, 'nombre' => 'Videos', 'publicado' => true,
            'medios' => ['partitura_score' => PartituraScore::vacia('Videos'), 'videos_multipista' => $videos],
        ]);
    }

    private function mp4(string $nombre = 'surdo.mp4'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, "\x00\x00\x00\x18ftypmp42".str_repeat("\x00", 800));
    }

    private function videoGuardado(string $id, string $instrumento = 'surdo_grave', int $orden = 1): array
    {
        return ['id' => $id, 'instrumento' => $instrumento, 'titulo' => 'V '.$id, 'archivo' => 'partituras/videos/1-abcdefghijklmnop.mp4', 'offset_seg' => 0, 'orden' => $orden];
    }

    private function editor(): \App\Models\User
    {
        $persona = $this->persona('Profe Edición');
        $user = $this->usuario('Profe Edición', $persona);
        $this->asignarPermiso($persona, 'partituras.edit');

        return $user;
    }

    public function test_sube_un_video_al_toque_en_la_carpeta_propia(): void
    {
        Storage::fake(PartituraAudio::disco());
        $toque = $this->toque();
        $this->actingAs($this->admin());

        $this->post(route('programa.toque.videos.store', $toque), [
            'archivo' => $this->mp4(), 'instrumento' => 'timbal', 'titulo' => 'Timbal de Lu', 'offset_seg' => 0.25,
        ])->assertRedirect()->assertSessionHas('status');

        $lista = $toque->fresh()->mediosNormalizados()['videos_multipista'];
        $this->assertCount(1, $lista);
        $this->assertSame('timbal', $lista[0]['instrumento']);
        $this->assertSame(0.25, $lista[0]['offset_seg']);
        $this->assertStringStartsWith(VideosMultipista::CARPETA.'/'.$toque->id.'-', $lista[0]['archivo']);
        Storage::disk(PartituraAudio::disco())->assertExists($lista[0]['archivo']);
    }

    public function test_rechaza_un_archivo_que_no_es_video(): void
    {
        Storage::fake(PartituraAudio::disco());
        $toque = $this->toque();
        $this->actingAs($this->admin());
        $tmp = tempnam(sys_get_temp_dir(), 'falso');
        file_put_contents($tmp, '<!DOCTYPE html><html><body><script>alert(1)</script></body></html>');
        $falso = new UploadedFile($tmp, 'truco.mp4', null, null, true);

        $this->post(route('programa.toque.videos.store', $toque), ['archivo' => $falso, 'instrumento' => 'voz'])
            ->assertSessionHasErrors('archivo');
        $this->assertSame([], $toque->fresh()->mediosNormalizados()['videos_multipista']);
    }

    public function test_rechaza_extensiones_que_no_son_video(): void
    {
        Storage::fake(PartituraAudio::disco());
        $toque = $this->toque();
        $this->actingAs($this->admin());

        $this->post(route('programa.toque.videos.store', $toque), ['archivo' => $this->mp4('x.exe'), 'instrumento' => 'voz'])
            ->assertSessionHasErrors('archivo');
    }

    public function test_no_admite_mas_de_seis(): void
    {
        Storage::fake(PartituraAudio::disco());
        $toque = $this->toque(array_map(fn ($i) => $this->videoGuardado('v'.str_repeat((string) $i, 8), 'voz', $i), range(1, 6)));
        $this->actingAs($this->admin());

        $this->post(route('programa.toque.videos.store', $toque), ['archivo' => $this->mp4(), 'instrumento' => 'voz'])
            ->assertSessionHasErrors('archivo');
        $this->assertCount(6, $toque->fresh()->mediosNormalizados()['videos_multipista']);
    }

    public function test_sin_permiso_de_edicion_no_se_puede_tocar_nada(): void
    {
        Storage::fake(PartituraAudio::disco());
        $toque = $this->toque([$this->videoGuardado('vaaaaaaaa')]);
        $this->actingAs($this->usuario('Sin permisos'));

        $this->post(route('programa.toque.videos.store', $toque), ['archivo' => $this->mp4(), 'instrumento' => 'voz'])->assertForbidden();
        $this->put(route('programa.toque.videos.update', [$toque, 'vaaaaaaaa']), ['titulo' => 'x'])->assertForbidden();
        $this->post(route('programa.toque.videos.mover', [$toque, 'vaaaaaaaa']), ['direccion' => 'abajo'])->assertForbidden();
        $this->delete(route('programa.toque.videos.destroy', [$toque, 'vaaaaaaaa']))->assertForbidden();
        $this->assertCount(1, $toque->fresh()->mediosNormalizados()['videos_multipista']);
    }

    public function test_quien_tiene_partituras_edit_puede_editar(): void
    {
        Storage::fake(PartituraAudio::disco());
        $toque = $this->toque([$this->videoGuardado('vaaaaaaaa')]);
        $this->actingAs($this->editor());

        $this->put(route('programa.toque.videos.update', [$toque, 'vaaaaaaaa']), [
            'titulo' => 'Repique izq', 'instrumento' => 'repique', 'offset_seg' => -0.4,
        ])->assertRedirect();

        $v = $toque->fresh()->mediosNormalizados()['videos_multipista'][0];
        $this->assertSame('Repique izq', $v['titulo']);
        $this->assertSame('repique', $v['instrumento']);
        $this->assertSame(-0.4, $v['offset_seg']);
    }

    public function test_reordena_y_quita_borrando_el_archivo(): void
    {
        Storage::fake(PartituraAudio::disco());
        $ruta = 'partituras/videos/'.'1-abcdefghijklmnop.mp4';
        Storage::disk(PartituraAudio::disco())->put($ruta, 'video');
        $toque = $this->toque([
            ['id' => 'va1111111', 'instrumento' => 'voz', 'titulo' => 'A', 'archivo' => $ruta, 'offset_seg' => 0, 'orden' => 1],
            ['id' => 'vb2222222', 'instrumento' => 'voz', 'titulo' => 'B', 'archivo' => 'partituras/videos/1-zyxwvutsrqponmlk.webm', 'offset_seg' => 0, 'orden' => 2],
        ]);
        $this->actingAs($this->admin());

        $this->post(route('programa.toque.videos.mover', [$toque, 'va1111111']), ['direccion' => 'abajo'])->assertRedirect();
        $this->assertSame(['vb2222222', 'va1111111'], array_column($toque->fresh()->mediosNormalizados()['videos_multipista'], 'id'));

        $this->delete(route('programa.toque.videos.destroy', [$toque, 'va1111111']))->assertRedirect();
        $lista = $toque->fresh()->mediosNormalizados()['videos_multipista'];
        $this->assertSame(['vb2222222'], array_column($lista, 'id'));
        $this->assertSame(1, $lista[0]['orden']);
        Storage::disk(PartituraAudio::disco())->assertMissing($ruta);
    }

    public function test_el_video_de_otro_toque_no_se_puede_editar(): void
    {
        $toque = $this->toque([$this->videoGuardado('vaaaaaaaa')]);
        $this->actingAs($this->admin());

        $this->put(route('programa.toque.videos.update', [$toque, 'vnoexiste']), ['titulo' => 'x'])->assertNotFound();
    }

    public function test_la_ficha_muestra_la_edicion_solo_a_quien_puede_editar(): void
    {
        $toque = $this->toque([$this->videoGuardado('vaaaaaaaa')]);

        $this->actingAs($this->editor());
        $this->get(route('programa.toque.show', $toque))->assertOk()
            ->assertSee('Editar los videos')->assertSee('data-videos-multipista', false);

        $this->actingAs($this->usuario('Sin permisos ver'));
        $this->get(route('programa.toque.show', $toque))->assertOk()->assertDontSee('Editar los videos');
    }
}
