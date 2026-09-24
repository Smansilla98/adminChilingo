<?php

namespace Tests\Feature;

use App\Models\BibliotecaItem;
use App\Models\Diseno;
use App\Models\DisenoKitAsset;
use App\Models\User;
use App\Policies\DisenoPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Escenarios;
use Tests\TestCase;

/**
 * Módulo Diseño con el editor OpenDesign: API del editor, dueño de cada diseño,
 * plantillas de estudio de solo lectura, kit de marca y compatibilidad con los
 * diseños del editor anterior.
 */
class DisenoOwnershipTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function disenador(string $nombre = 'Diseñadora'): User
    {
        $user = $this->usuario($nombre);
        $this->asignarPermiso($user->persona, 'disenos.manage');

        return $user->fresh();
    }

    private function diseno(?User $duenio, string $titulo = 'Diseño', ?array $canvas = null): Diseno
    {
        return Diseno::query()->create([
            'titulo' => $titulo, 'formato' => 'flyer_feed', 'ancho' => 1080, 'alto' => 1350,
            'canvas_json' => $canvas ?? ['version' => '6.0.0', 'objects' => []],
            'user_id' => $duenio?->id,
        ]);
    }

    public function test_el_editor_carga_con_su_configuracion(): void
    {
        $this->actingAs($this->disenador())->get('/disenos')->assertOk()
            ->assertSee('window.__OPENDESIGN__', false)
            ->assertSee('disenos\/api', false);
        $this->actingAs($this->disenador('Otra'))->get('/disenos/design/5')->assertOk();
    }

    public function test_sin_permiso_no_entra_al_editor_ni_a_la_api(): void
    {
        $user = $this->usuario('Sin permiso');
        $this->actingAs($user)->get('/disenos')->assertForbidden();
        $this->actingAs($user)->getJson('/disenos/api/designs')->assertForbidden();
    }

    public function test_crear_editar_con_paginas_y_eliminar(): void
    {
        $user = $this->disenador();
        $canvas = json_encode(['version' => '6.0.0', 'objects' => [['type' => 'rect', 'width' => 10, 'height' => 10]]]);

        $d = $this->actingAs($user)->postJson('/disenos/api/designs', ['name' => 'Flyer muestra', 'canvas_json' => $canvas, 'width' => 1080, 'height' => 1350])
            ->assertOk()->json();
        $this->assertDatabaseHas('disenos', ['id' => $d['id'], 'user_id' => $user->id, 'formato' => 'flyer_feed']);

        $completo = $this->getJson("/disenos/api/designs/{$d['id']}")->assertOk()->json();
        $this->assertCount(1, $completo['pages']);
        $pagina = $completo['pages'][0]['id'];

        $nueva = $this->postJson("/disenos/api/designs/{$d['id']}/pages", ['after_sort_order' => 0])->assertOk()->json();
        $this->assertSame('Página 2', $nueva['title']);
        $this->postJson("/disenos/api/pages/{$pagina}/duplicate")->assertOk();
        $this->putJson("/disenos/api/pages/{$pagina}", ['title' => 'Portada', 'canvas_json' => $canvas])->assertOk()->assertJsonPath('title', 'Portada');
        $this->assertCount(3, $this->getJson("/disenos/api/designs/{$d['id']}")->json('pages'));

        $this->putJson("/disenos/api/designs/{$d['id']}", ['name' => 'Flyer final', 'canvas_json' => '{no-es-json'])->assertStatus(422);
        $this->putJson("/disenos/api/designs/{$d['id']}", ['name' => 'Flyer final'])->assertOk()->assertJsonPath('name', 'Flyer final');

        $this->deleteJson("/disenos/api/designs/{$d['id']}")->assertOk();
        $this->assertDatabaseMissing('disenos', ['id' => $d['id']]);
        $this->assertDatabaseCount('diseno_paginas', 0);
    }

    public function test_no_se_elimina_la_unica_pagina(): void
    {
        $user = $this->disenador();
        $d = $this->actingAs($user)->postJson('/disenos/api/designs', ['name' => 'Uno'])->json();
        $pagina = $this->getJson("/disenos/api/designs/{$d['id']}")->json('pages.0.id');

        $this->deleteJson("/disenos/api/pages/{$pagina}")->assertStatus(400);
    }

    public function test_cada_uno_ve_y_edita_solo_lo_suyo_y_admin_todo(): void
    {
        $ana = $this->disenador('Ana');
        $beto = $this->disenador('Beto');
        $deAna = $this->diseno($ana, 'De Ana');
        $admin = $this->admin();

        $this->actingAs($beto)->getJson("/disenos/api/designs/{$deAna->id}")->assertForbidden();
        $this->actingAs($beto)->putJson("/disenos/api/designs/{$deAna->id}", ['name' => 'Robado'])->assertForbidden();
        $this->actingAs($beto)->deleteJson("/disenos/api/designs/{$deAna->id}")->assertForbidden();
        $this->assertSame([], $this->actingAs($beto)->getJson('/disenos/api/designs')->json());

        $this->actingAs($admin)->getJson('/disenos/api/designs')->assertOk()->assertJsonFragment(['name' => 'De Ana']);
        $this->actingAs($admin)->putJson("/disenos/api/designs/{$deAna->id}", ['name' => 'Revisado'])->assertOk();
        $this->actingAs($admin)->deleteJson("/disenos/api/designs/{$deAna->id}")->assertOk();
    }

    public function test_paginas_ajenas_no_se_tocan_por_id(): void
    {
        $ana = $this->disenador('Ana');
        $d = $this->actingAs($ana)->postJson('/disenos/api/designs', ['name' => 'Privado'])->json();
        $pagina = $this->getJson("/disenos/api/designs/{$d['id']}")->json('pages.0.id');

        $beto = $this->disenador('Beto');
        $this->actingAs($beto)->putJson("/disenos/api/pages/{$pagina}", ['title' => 'x'])->assertForbidden();
        $this->actingAs($beto)->postJson("/disenos/api/pages/{$pagina}/duplicate")->assertForbidden();
        $this->actingAs($beto)->postJson("/disenos/api/designs/{$d['id']}/pages")->assertForbidden();
    }

    public function test_disenos_del_editor_anterior_se_abren_con_una_pagina(): void
    {
        $user = $this->disenador();
        $viejo = $this->diseno($user, 'Viejo', ['version' => '6.9.1', 'objects' => [['type' => 'Textbox', 'text' => 'Hola']]]);

        $r = $this->actingAs($user)->getJson("/disenos/api/designs/{$viejo->id}")->assertOk();
        $this->assertCount(1, $r->json('pages'));
        $this->assertStringContainsString('Hola', $r->json('pages.0.canvas_json'));
    }

    public function test_plantillas_incluyen_las_de_la_chilinga_y_las_de_estudio_de_solo_lectura(): void
    {
        $user = $this->disenador();
        $estudio = $this->diseno(null, 'Plantilla estudio');

        $plantillas = collect($this->actingAs($user)->getJson('/disenos/api/templates')->assertOk()->json());
        $this->assertTrue($plantillas->contains('id', 'chl-show'));
        $this->assertTrue($plantillas->contains('id', 'estudio-'.$estudio->id));
        $this->actingAs($user)->getJson('/disenos/api/templates/chl-show')->assertOk()->assertJsonPath('width', 1080);

        // Se puede ver para copiarla, pero no modificarla.
        $this->assertTrue((new DisenoPolicy)->view($user, $estudio));
        $this->actingAs($user)->putJson("/disenos/api/designs/{$estudio->id}", ['name' => 'x'])->assertForbidden();
    }

    public function test_subir_imagen_guarda_y_rechaza_svg(): void
    {
        Storage::fake('public');
        $user = $this->disenador();

        $url = $this->actingAs($user)->post('/disenos/api/uploads', ['file' => UploadedFile::fake()->image('foto.png', 200, 200)], ['Accept' => 'application/json'])
            ->assertOk()->json('url');
        $this->assertStringContainsString('disenos/medios/'.$user->id, $url);

        $svg = UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->post('/disenos/api/uploads', ['file' => $svg], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_miniatura_se_guarda_al_actualizar(): void
    {
        Storage::fake('public');
        $user = $this->disenador();
        $d = $this->diseno($user);
        $png = 'data:image/png;base64,'.base64_encode(UploadedFile::fake()->image('t.png', 20, 20)->getContent());

        $this->actingAs($user)->putJson("/disenos/api/designs/{$d->id}", ['thumbnail_data' => $png])->assertOk();

        Storage::disk('public')->assertExists("disenos/previews/{$d->id}.png");
        $this->assertNotNull($this->getJson("/disenos/api/designs/{$d->id}")->json('thumbnail_url'));
    }

    public function test_marca_lista_logos_kit_y_biblioteca(): void
    {
        $user = $this->disenador();
        DisenoKitAsset::query()->create(['titulo' => 'Logo sede', 'path' => 'disenos/kit/a.png', 'mime' => 'image/png', 'bytes' => 10]);
        BibliotecaItem::query()->create(['titulo' => 'Foto show', 'tipo' => 'imagen', 'path' => 'biblioteca/f.jpg', 'mime' => 'image/jpeg', 'estado' => 'publicado']);

        $grupos = collect($this->actingAs($user)->getJson('/disenos/api/marca')->assertOk()->json('grupos'))->keyBy('clave');
        $this->assertNotEmpty($grupos['logos']['items']);
        $this->assertSame('Logo sede', $grupos['kit']['items'][0]['label']);
        $this->assertSame('Foto show', $grupos['biblioteca']['items'][0]['label']);
    }

    public function test_kit_de_marca_solo_lo_gestiona_administracion(): void
    {
        Storage::fake('public');
        $user = $this->disenador();
        $admin = $this->admin();
        $archivo = fn () => UploadedFile::fake()->image('kit.png', 100, 100);

        $this->actingAs($user)->post('/disenos/api/marca/kit', ['archivo' => $archivo()], ['Accept' => 'application/json'])->assertForbidden();

        $id = $this->actingAs($admin)->post('/disenos/api/marca/kit', ['archivo' => $archivo(), 'titulo' => 'Logo'], ['Accept' => 'application/json'])
            ->assertOk()->json('id');
        $this->actingAs($user)->deleteJson("/disenos/api/marca/kit/{$id}")->assertForbidden();
        $this->actingAs($admin)->deleteJson("/disenos/api/marca/kit/{$id}")->assertOk();
        $this->assertDatabaseMissing('diseno_kit_assets', ['id' => $id]);
    }
}
