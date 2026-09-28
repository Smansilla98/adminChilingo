<?php

namespace Tests\Feature\Api;

use App\Models\BibliotecaItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

class BibliotecaApiTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_publicar_buscar_y_descargar(): void
    {
        Storage::fake('comprobantes');
        $user = $this->usuario('Ana');
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/biblioteca', ['titulo' => 'Sin nada'])->assertStatus(422)->assertJsonValidationErrors('archivo');
        $id = $this->post('/api/v1/biblioteca', ['titulo' => 'Foto del corso', 'hashtags' => '#corso #2026', 'archivo' => UploadedFile::fake()->image('corso.jpg')], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.tipo', 'imagen')->assertJsonPath('data.autor', 'Ana')->json('data.id');
        $this->postJson('/api/v1/biblioteca', ['titulo' => 'Video', 'url' => 'https://youtube.com/watch?v=x'])->assertCreated()->assertJsonPath('data.tipo', 'enlace');

        $this->assertSame(1, $this->getJson('/api/v1/biblioteca?tag=corso')->assertOk()->json('meta.total'));
        $this->assertSame(1, $this->getJson('/api/v1/biblioteca?tipo=enlace')->json('meta.total'));
        $this->get("/api/v1/biblioteca/{$id}/archivo")->assertOk();
        $this->postJson("/api/v1/biblioteca/{$id}/visibilidad")->assertForbidden();
        $this->deleteJson("/api/v1/biblioteca/{$id}")->assertForbidden();
    }

    public function test_moderacion_oculta_y_elimina(): void
    {
        Storage::fake('comprobantes');
        $item = BibliotecaItem::query()->create(['titulo' => 'Material', 'tipo' => 'enlace', 'url' => 'https://x.test', 'estado' => 'publicado']);
        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/biblioteca/{$item->id}/visibilidad")->assertOk()->assertJsonPath('data.estado', 'oculto');
        $this->assertSame(1, $this->getJson('/api/v1/biblioteca?estado=oculto')->json('meta.total'));

        Sanctum::actingAs($this->usuario('Otra'));
        $this->getJson("/api/v1/biblioteca/{$item->id}")->assertNotFound();
        $this->assertSame(0, $this->getJson('/api/v1/biblioteca?estado=oculto')->json('meta.total'), 'sin moderación solo ve publicados');

        Sanctum::actingAs($this->admin('Admin 2'));
        $this->deleteJson("/api/v1/biblioteca/{$item->id}")->assertOk();
        $this->assertNull(BibliotecaItem::query()->find($item->id));
    }
}
