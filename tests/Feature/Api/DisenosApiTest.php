<?php

namespace Tests\Feature\Api;

use App\Models\Diseno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

class DisenosApiTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function disenador(string $nombre = 'Diseñadora')
    {
        $u = $this->usuario($nombre);
        $this->asignarPermiso($u->persona, 'disenos.manage');

        return $u->fresh();
    }

    public function test_desde_plantilla_edicion_de_contenido_paginas_y_baja(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->disenador());

        $plantillas = $this->getJson('/api/v1/disenos/plantillas')->assertOk()->json();
        $this->assertNotEmpty($plantillas);
        $p = $plantillas[0];

        $d = $this->postJson('/api/v1/disenos', ['name' => 'Flyer muestra', 'canvas_json' => $p['canvas_json'], 'width' => $p['width'], 'height' => $p['height']])->assertOk()->json();
        $ficha = $this->getJson("/api/v1/disenos/{$d['id']}")->assertOk()->json();
        $pagina = $ficha['pages'][0]['id'];

        $canvas = json_decode($ficha['pages'][0]['canvas_json'], true);
        $canvas['objects'][] = ['type' => 'textbox', 'left' => 10, 'top' => 10, 'width' => 300, 'text' => 'Sábado 20 h', 'fill' => '#ffffff', 'fontSize' => 40];
        $this->putJson("/api/v1/disenos/paginas/{$pagina}", ['canvas_json' => json_encode($canvas)])->assertOk();
        $this->putJson("/api/v1/disenos/paginas/{$pagina}", ['canvas_json' => '{no es json'])->assertStatus(422);

        $copia = $this->postJson("/api/v1/disenos/paginas/{$pagina}/duplicar")->assertOk()->json('id');
        $this->assertCount(2, $this->getJson("/api/v1/disenos/{$d['id']}")->json('pages'));
        $this->deleteJson("/api/v1/disenos/paginas/{$copia}")->assertOk();
        $this->deleteJson("/api/v1/disenos/paginas/{$pagina}")->assertStatus(400);

        $url = $this->post('/api/v1/disenos/imagenes', ['file' => UploadedFile::fake()->image('foto.png', 200, 200)], ['Accept' => 'application/json'])->assertOk()->json('url');
        $this->assertStringContainsString('/disenos/medios/', $url);

        $this->putJson("/api/v1/disenos/{$d['id']}", ['name' => 'Flyer final'])->assertOk()->assertJsonPath('name', 'Flyer final');
        $this->deleteJson("/api/v1/disenos/{$d['id']}")->assertOk();
        $this->assertNull(Diseno::query()->find($d['id']));
    }

    public function test_no_se_edita_ni_ve_el_diseno_de_otra_persona(): void
    {
        $duenia = $this->disenador('Duenia');
        $ajeno = Diseno::query()->create(['titulo' => 'Ajeno', 'formato' => 'custom', 'ancho' => 100, 'alto' => 100, 'user_id' => $duenia->id]);
        Sanctum::actingAs($this->disenador('Otra'));

        $this->getJson("/api/v1/disenos/{$ajeno->id}")->assertForbidden();
        $this->putJson("/api/v1/disenos/{$ajeno->id}", ['name' => 'Hack'])->assertForbidden();
        $this->deleteJson("/api/v1/disenos/{$ajeno->id}")->assertForbidden();
        $this->assertSame([], $this->getJson('/api/v1/disenos')->assertOk()->json());
        $this->postJson('/api/v1/disenos/marca/kit', [])->assertForbidden();

        $sinPermiso = $this->usuario('Sin');
        Sanctum::actingAs($sinPermiso);
        $this->getJson('/api/v1/disenos')->assertForbidden();
    }
}
