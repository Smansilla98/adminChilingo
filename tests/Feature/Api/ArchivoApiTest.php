<?php

namespace Tests\Feature\Api;

use App\Models\ArchivoAcontecimiento;
use App\Models\ArchivoCapitulo;
use App\Models\ArchivoFoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

class ArchivoApiTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('comprobantes');
    }

    private function imagen(string $nombre = 'foto.jpg'): UploadedFile
    {
        $img = imagecreatetruecolor(900, 600);
        imagefill($img, 0, 0, imagecolorallocate($img, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
        $ruta = tempnam(sys_get_temp_dir(), 'arch');
        imagejpeg($img, $ruta, 85);

        return new UploadedFile($ruta, $nombre, 'image/jpeg', null, true);
    }

    public function test_lectura_publica_sin_cuenta(): void
    {
        $cap = ArchivoCapitulo::query()->create(['titulo' => 'Fundación', 'slug' => 'fundacion', 'anio_desde' => 1995, 'publicado' => true]);
        $a = ArchivoAcontecimiento::query()->create(['titulo' => 'Primer ensayo', 'slug' => 'primer-ensayo', 'anio' => 1995, 'capitulo_id' => $cap->id, 'publicado' => true]);
        ArchivoFoto::query()->create(['slug' => 'publica', 'titulo' => 'Pública', 'path' => 'x.jpg', 'estado' => 'publicada', 'anio' => 1995, 'acontecimiento_id' => $a->id]);
        ArchivoFoto::query()->create(['slug' => 'borrador', 'titulo' => 'Borrador', 'path' => 'y.jpg', 'estado' => 'borrador', 'anio' => 1995]);

        $this->getJson('/api/v1/archivo')->assertOk()->assertJsonPath('linea.total_fotos', 1)->assertJsonPath('capitulos.0.slug', 'fundacion');
        $this->getJson('/api/v1/archivo/timeline')->assertOk()->assertJsonPath('decadas.0.decada', 1990);
        $this->getJson('/api/v1/archivo/capitulos/fundacion')->assertOk()->assertJsonPath('data.acontecimientos.0.fotos.0.titulo', 'Pública');
        $this->getJson('/api/v1/archivo/eventos/primer-ensayo')->assertOk()->assertJsonCount(1, 'data.fotos');
        $this->getJson('/api/v1/archivo/fotos?anio=1995')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/archivo/fotos/publica')->assertOk()->assertJsonPath('data.titulo', 'Pública');
        $this->getJson('/api/v1/archivo/fotos/borrador')->assertNotFound();
        $this->getJson('/api/v1/archivo/aportes')->assertUnauthorized();
    }

    public function test_aporte_y_moderacion_por_api(): void
    {
        $user = $this->usuario('Juan');
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/archivo/catalogo')->assertOk()->assertJsonPath('permisos.aportar', true)->assertJsonPath('permisos.gestionar', false);

        $id = $this->post('/api/v1/archivo/aportes', [
            'archivo' => $this->imagen(), 'titulo' => 'Ensayo', 'anio' => 1998, 'enviar' => 1,
            'personas' => [['nombre' => 'María']], 'tags' => ['calle'],
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.estado', 'pendiente')->assertJsonPath('data.acciones.editar', true)->assertJsonPath('data.acciones.moderar', false)
            ->json('data.id');
        $this->assertStringContainsString('/api/v1/archivo/imagen/', $this->getJson('/api/v1/archivo/aportes')->json('data.0.imagen.chica'));
        $this->get("/api/v1/archivo/imagen/{$id}/400")->assertOk();
        $this->getJson('/api/v1/archivo/gestion/moderacion')->assertForbidden();

        $mod = $this->admin();
        Sanctum::actingAs($mod);
        $this->getJson('/api/v1/archivo/gestion/moderacion')->assertOk()->assertJsonPath('meta.conteos.pendiente', 1)->assertJsonPath('data.0.aportante_nombre', 'Juan');
        $this->postJson("/api/v1/archivo/gestion/fotos/{$id}/estado", ['accion' => 'cambios', 'notas' => '¿Dónde fue?'])->assertOk()->assertJsonPath('data.estado', 'cambios');

        Sanctum::actingAs($user);
        $this->getJson("/api/v1/archivo/aportes/{$id}")->assertOk()->assertJsonPath('data.notas_revision', '¿Dónde fue?');
        $this->putJson("/api/v1/archivo/aportes/{$id}", ['lugar' => 'Banfield', 'enviar' => true])->assertOk()->assertJsonPath('data.estado', 'pendiente');

        Sanctum::actingAs($mod);
        $this->postJson("/api/v1/archivo/gestion/fotos/{$id}/estado", ['accion' => 'aprobar'])->assertOk()->assertJsonPath('data.estado', 'publicada');
        $this->getJson("/api/v1/archivo/fotos/{$id}")->assertOk()->assertJsonPath('data.lugar', 'Banfield');

        Sanctum::actingAs($user);
        $this->putJson("/api/v1/archivo/aportes/{$id}", ['titulo' => 'x'])->assertForbidden();
        $this->deleteJson("/api/v1/archivo/aportes/{$id}")->assertForbidden();
    }

    public function test_gestion_por_api_con_lote_orden_y_alcance(): void
    {
        $palomar = $this->sede('Palomar');
        $banfield = $this->sede('Banfield');
        $archivista = $this->usuario('Archivista');
        $this->asignarRol($archivista->persona, 'archivista', 'sede', $palomar);
        Sanctum::actingAs($archivista);

        $this->postJson('/api/v1/archivo/gestion/capitulos', ['titulo' => 'No'])->assertForbidden();
        $this->postJson('/api/v1/archivo/gestion/eventos', ['titulo' => 'Ajeno', 'anio' => 2000, 'sede_id' => $banfield->id])->assertForbidden();
        $this->assertSame(0, ArchivoAcontecimiento::query()->count(), 'no se guarda nada fuera del alcance');
        $evento = $this->postJson('/api/v1/archivo/gestion/eventos', ['titulo' => 'Apertura de Palomar', 'anio' => 2003, 'sede_id' => $palomar->id, 'publicado' => true])
            ->assertCreated()->json('data.id');

        $ids = [];
        foreach ([1, 2] as $i) {
            $ids[] = $this->post('/api/v1/archivo/gestion/fotos', ['archivo' => $this->imagen("p{$i}.jpg"), 'sede_id' => $palomar->id, 'acontecimiento_id' => $evento], ['Accept' => 'application/json'])
                ->assertCreated()->json('data.id');
        }
        $this->post('/api/v1/archivo/gestion/fotos', ['archivo' => $this->imagen(), 'sede_id' => $banfield->id], ['Accept' => 'application/json'])->assertForbidden();

        $this->postJson('/api/v1/archivo/gestion/fotos/lote', ['ids' => $ids, 'accion' => 'aplicar', 'anio' => 2003, 'tags' => '#apertura', 'publicar' => true])
            ->assertOk()->assertJsonPath('hechas', 2)->assertJsonPath('omitidas', 0);
        $this->assertSame(2, ArchivoFoto::query()->publicadas()->where('anio', 2003)->count());
        $this->postJson('/api/v1/archivo/gestion/fotos/lote', ['ids' => $ids, 'accion' => 'aplicar', 'sede_id' => $banfield->id])->assertForbidden();

        $this->postJson('/api/v1/archivo/gestion/fotos/orden', ['ids' => array_reverse($ids)])->assertOk();
        $this->getJson("/api/v1/archivo/gestion/fotos?acontecimiento={$evento}")->assertOk()
            ->assertJsonPath('meta.ordenable', true)->assertJsonPath('data.0.id', $ids[1]);
        $this->getJson('/api/v1/archivo/gestion/resumen')->assertOk()->assertJsonPath('totales.publicadas', 2);
    }
}
