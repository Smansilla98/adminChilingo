<?php

namespace Tests\Feature;

use App\Domain\Personas\PersonaService;
use App\Models\ArchivoAcontecimiento;
use App\Models\ArchivoCapitulo;
use App\Models\ArchivoFoto;
use App\Models\ArchivoFotoPersona;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Escenarios;
use Tests\TestCase;

class ArchivoHistoricoTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('comprobantes');
    }

    /** Imagen real y distinta en cada llamada (el archivo detecta duplicados por hash). */
    private function imagen(string $nombre = 'foto.jpg', int $w = 1600, int $h = 1000): UploadedFile
    {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
        imagefilledrectangle($img, random_int(0, $w / 2), random_int(0, $h / 2), $w - 1, $h - 1, imagecolorallocate($img, random_int(0, 255), 90, 30));
        $ruta = tempnam(sys_get_temp_dir(), 'arch');
        str_ends_with($nombre, '.png') ? imagepng($img, $ruta) : imagejpeg($img, $ruta, 85);

        return new UploadedFile($ruta, $nombre, str_ends_with($nombre, '.png') ? 'image/png' : 'image/jpeg', null, true);
    }

    private function subirComoEquipo(string $nombre = 'foto.jpg', array $extra = []): ArchivoFoto
    {
        $id = $this->post(route('archivo.gestion.fotos.store'), ['archivo' => $this->imagen($nombre)] + $extra, ['Accept' => 'application/json'])
            ->assertCreated()->json('data.id');

        return ArchivoFoto::query()->findOrFail($id);
    }

    public function test_recorrido_completo_del_equipo_hasta_la_experiencia_publica(): void
    {
        $admin = $this->admin();
        $sede = $this->sede('Banfield');
        $dani = $this->persona('Dani Buira');
        $this->actingAs($admin);

        // Capítulo → acontecimiento → fotos.
        $this->post(route('archivo.gestion.capitulos.store'), ['titulo' => 'Los primeros años', 'anio_desde' => 1995, 'anio_hasta' => 1999, 'publicado' => 1])
            ->assertRedirect();
        $capitulo = ArchivoCapitulo::query()->firstOrFail();
        $this->post(route('archivo.gestion.eventos.store'), [
            'titulo' => 'Primeros ensayos', 'anio' => 1996, 'capitulo_id' => $capitulo->id, 'sede_id' => $sede->id,
            'lugar' => 'Plaza de Banfield', 'descripcion' => 'Los tambores salen a la calle.', 'publicado' => 1,
        ])->assertRedirect();
        $acontecimiento = ArchivoAcontecimiento::query()->firstOrFail();
        $this->assertSame('primeros-ensayos', $acontecimiento->slug);

        $f1 = $this->subirComoEquipo('IMG_0001.jpg');
        $f2 = $this->subirComoEquipo('ensayo en la plaza.jpg');
        $this->assertSame('borrador', $f1->estado);
        $this->assertNull($f1->titulo, 'los nombres de cámara no son título');
        $this->assertSame('Ensayo en la plaza', $f2->titulo);
        $this->assertSame([400, 800, 1200, 2048], array_keys($f1->derivados), 'derivados web generados');
        $this->assertSame(1600, $f1->derivados['2048']['w'], 'nunca se agranda el original');
        Storage::disk('comprobantes')->assertExists($f1->path);
        $this->assertStringStartsWith('data:image/', $f1->placeholder);

        // Edición masiva: año, acontecimiento, personas, tags, crédito y publicación.
        $this->post(route('archivo.gestion.fotos.lote'), [
            'ids' => [$f1->id, $f2->id], 'accion' => 'aplicar', 'anio' => 1996, 'acontecimiento_id' => $acontecimiento->id,
            'credito' => 'Archivo La Chilinga', 'tags' => '#ensayo #tambores',
            'personas' => [['persona_id' => $dani->id, 'detalle' => 'al centro'], ['nombre' => 'Juan Pérez']],
            'publicar' => 1,
        ])->assertRedirect()->assertSessionHas('success');

        $f1->refresh();
        $this->assertSame('publicada', $f1->estado);
        $this->assertSame(1996, $f1->anio);
        $this->assertSame($capitulo->id, $f1->capitulo_id, 'hereda el capítulo del acontecimiento');
        $this->assertSame($sede->id, $f1->sede_id, 'hereda la sede del acontecimiento');
        $this->assertEqualsCanonicalizing(['ensayo', 'tambores'], $f1->tags->pluck('nombre')->all());
        $this->assertCount(2, $f1->personas);

        // Pantallas de gestión (regresión: un @endif mal cerrado rompía la ficha).
        $this->get(route('archivo.gestion.fotos.edit', $f1))->assertOk()->assertSee('carga del equipo');
        $this->get(route('archivo.gestion.fotos'))->assertOk();
        $this->get(route('archivo.gestion.tablero'))->assertOk();
        $this->get(route('archivo.gestion.eventos.edit', $acontecimiento))->assertOk();
        $this->get(route('archivo.gestion.capitulos.edit', $capitulo))->assertOk();

        // Orden dentro del acontecimiento (drag & drop).
        $this->postJson(route('archivo.gestion.fotos.orden'), ['ids' => [$f2->id, $f1->id]])->assertOk();
        $this->assertSame([$f2->id, $f1->id], $acontecimiento->fotos()->pluck('id')->all());

        // Público, sin sesión. Sin consultas perezosas (N+1) en las páginas públicas.
        auth()->logout();
        ArchivoFoto::query()->whereKey($f1->id)->update(['titulo' => null]);
        Model::preventLazyLoading();
        $this->get(route('archivo.index'))->assertOk()->assertSee('Los primeros años')->assertSee('Primeros ensayos')
            ->assertSee('og:image', false)->assertSee('rel="canonical"', false);
        $this->get(route('archivo.anio', 1996))->assertOk()->assertSee('1996');
        $this->get(route('archivo.anio', 1980))->assertNotFound();
        $this->get(route('archivo.capitulo', $capitulo->slug))->assertOk()->assertSee('Primeros ensayos');
        $this->get(route('archivo.acontecimiento', $acontecimiento->slug))->assertOk()->assertSee('Plaza de Banfield')->assertSee('"@type":"Event"', false);
        $this->get(route('archivo.foto', $f2->slug))->assertOk()
            ->assertSee('Dani Buira')->assertSee('al centro')->assertSee('Juan Pérez')->assertSee('#ensayo')
            ->assertSee('Archivo La Chilinga')->assertSee('"@type":"Photograph"', false);
        $this->get(route('archivo.historia'))->assertOk()->assertSee('Primeros ensayos');

        // Búsqueda y filtros combinados.
        $this->get(route('archivo.buscar', ['q' => 'Dani']))->assertOk()->assertSee($f2->tituloVisible());
        $this->get(route('archivo.buscar', ['q' => 'Banfield']))->assertOk()->assertSee('Primeros ensayos');
        $this->get(route('archivo.buscar', ['persona' => (string) $dani->id, 'tags' => ['ensayo', 'tambores'], 'decada' => 1990]))->assertOk()->assertSee($f2->tituloVisible());
        $this->get(route('archivo.buscar', ['tags' => ['gira']]))->assertOk()->assertDontSee($f2->tituloVisible());

        Model::preventLazyLoading(false);

        // Imagen pública cacheable.
        $this->get(route('archivo.imagen', ['foto' => $f1->id, 'ancho' => 800]))->assertOk()
            ->assertHeader('Content-Type', 'image/webp');
    }

    public function test_aporte_de_usuario_con_moderacion_completa(): void
    {
        $user = $this->usuario('Juan Pérez');
        $moderador = $this->admin('Moderadora');

        $this->actingAs($user);
        $this->get(route('archivo.aportar'))->assertOk()->assertSee('Compartí un recuerdo');
        $id = $this->post(route('archivo.aportes.subir'), ['archivo' => $this->imagen('banfield.jpg')], ['Accept' => 'application/json'])
            ->assertCreated()->json('data.id');

        // Sin año no se puede enviar: queda en borrador con aviso.
        $this->post(route('archivo.aportes.lote'), ['ids' => [$id], 'enviar' => 1, 'descripcion' => 'Ensayo'])
            ->assertRedirect(route('archivo.aportes.index'));
        $this->assertSame('borrador', ArchivoFoto::query()->find($id)->estado);

        $this->post(route('archivo.aportes.lote'), ['ids' => [$id], 'enviar' => 1, 'anio' => 1998, 'lugar' => 'Banfield', 'fotografo' => 'Desconocido', 'fuente' => 'archivo_personal'])
            ->assertRedirect();
        $foto = ArchivoFoto::query()->find($id);
        $this->assertSame('pendiente', $foto->estado);
        $this->assertSame($user->id, $foto->aportada_por);
        $this->assertFalse($foto->mostrar_aportante, 'por defecto el aportante es anónimo');
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $user->id)->count(), 'aviso de recepción');

        // No es pública todavía, ni su imagen.
        auth()->logout();
        $this->get(route('archivo.foto', $foto->slug))->assertNotFound();
        $this->get(route('archivo.imagen', ['foto' => $foto->id, 'ancho' => 400]))->assertNotFound();

        // La moderadora pide cambios.
        $this->actingAs($moderador);
        $this->get(route('archivo.gestion.moderacion'))->assertOk()->assertSee('Juan Pérez')->assertSee('1998');
        $this->post(route('archivo.gestion.fotos.estado', $foto), ['accion' => 'cambios'])->assertSessionHasErrors('notas');
        $this->post(route('archivo.gestion.fotos.estado', $foto), ['accion' => 'cambios', 'notas' => 'Indicá quiénes aparecen.'])->assertRedirect();
        $this->assertSame('cambios', $foto->fresh()->estado);

        // El aportante lo ve, corrige y reenvía.
        $this->actingAs($user);
        $this->get(route('archivo.aportes.show', $foto))->assertOk()->assertSee('Indicá quiénes aparecen.');
        $this->put(route('archivo.aportes.update', $foto), [
            'titulo' => 'Ensayo en Banfield', 'anio' => 1998, 'mostrar_aportante' => 1, 'enviar' => 1,
            'personas' => [['nombre' => 'María González']],
        ])->assertRedirect();
        $this->assertSame('pendiente', $foto->fresh()->estado);

        // Aprobación: se publica y conserva la procedencia.
        $this->actingAs($moderador);
        $this->post(route('archivo.gestion.fotos.estado', $foto), ['accion' => 'aprobar'])->assertRedirect();
        $foto->refresh();
        $this->assertSame('publicada', $foto->estado);
        $this->assertSame($moderador->id, $foto->revisada_por);
        $this->assertSame(['creada', 'enviada', 'cambios', 'enviada', 'aprobada'], $foto->revisiones()->reorder('id')->pluck('accion')->all());

        $this->actingAs($user);
        $this->put(route('archivo.aportes.update', $foto), ['titulo' => 'Cambio tardío'])->assertForbidden();
        $this->delete(route('archivo.aportes.destroy', $foto))->assertForbidden();

        auth()->logout();
        $this->get(route('archivo.foto', $foto->slug))->assertOk()
            ->assertSee('Aportado por')->assertSee('Juan Pérez')->assertSee('Desconocido')->assertSee('Archivo personal')->assertSee('María González');
        $this->assertSame(['María González'], $this->getJson(route('archivo.personas', ['q' => 'mar']))->json('data.*.nombre'));
    }

    public function test_permisos_y_alcance(): void
    {
        $palomar = $this->sede('Palomar');
        $banfield = $this->sede('Banfield');
        $comun = $this->usuario('Común');
        $otro = $this->usuario('Otro');
        $archivista = $this->usuario('Archivista Palomar');
        $this->asignarRol($archivista->persona, 'archivista', 'sede', $palomar);

        // Un usuario común no entra a la gestión ni modera.
        $this->actingAs($comun);
        $this->get(route('archivo.gestion.tablero'))->assertForbidden();
        $aporte = ArchivoFoto::query()->findOrFail($this->post(route('archivo.aportes.subir'), ['archivo' => $this->imagen()], ['Accept' => 'application/json'])->json('data.id'));
        $this->post(route('archivo.gestion.fotos.estado', $aporte), ['accion' => 'aprobar'])->assertForbidden();

        // Nadie ve ni toca el aporte de otro.
        $this->actingAs($otro);
        $this->get(route('archivo.aportes.show', $aporte))->assertNotFound();
        $this->put(route('archivo.aportes.update', $aporte), ['titulo' => 'x'])->assertNotFound();
        $this->get(route('archivo.original', $aporte))->assertForbidden();
        $this->get(route('archivo.imagen', ['foto' => $aporte->id, 'ancho' => 400]))->assertNotFound();

        // Archivista con alcance de sede: opera en su sede, no en otra; no crea capítulos.
        $this->actingAs($archivista);
        $this->get(route('archivo.gestion.tablero'))->assertOk();
        $mia = $this->subirComoEquipo();
        $this->put(route('archivo.gestion.fotos.update', $mia), ['titulo' => 'De Palomar', 'sede_id' => $palomar->id])->assertRedirect();
        $ajena = ArchivoFoto::query()->create(['slug' => 'ajena', 'path' => 'x.jpg', 'estado' => 'borrador', 'sede_id' => $banfield->id]);
        $this->put(route('archivo.gestion.fotos.update', $ajena), ['titulo' => 'No'])->assertForbidden();
        $this->post(route('archivo.gestion.fotos.estado', $ajena), ['accion' => 'publicar'])->assertForbidden();
        $this->post(route('archivo.gestion.fotos.lote'), ['ids' => [$mia->id, $ajena->id], 'accion' => 'publicar'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Publicamos 1') && str_contains($m, '1 quedaron sin cambios'));
        $this->assertSame('borrador', $ajena->fresh()->estado);
        $this->get(route('archivo.gestion.capitulos.create'))->assertForbidden();
        $this->get(route('archivo.gestion.fotos'))->assertOk()->assertSee('De Palomar')->assertDontSee('ajena');
        $this->get(route('archivo.gestion.personas.buscar', ['q' => 'Co']))->assertOk();
    }

    public function test_duplicados_se_avisan_y_se_pueden_confirmar(): void
    {
        $this->actingAs($this->admin());
        $archivo = $this->imagen('original.jpg');
        $copia = new UploadedFile($archivo->getRealPath(), 'copia.jpg', 'image/jpeg', null, true);
        $this->post(route('archivo.gestion.fotos.store'), ['archivo' => $archivo], ['Accept' => 'application/json'])->assertCreated();

        $this->post(route('archivo.gestion.fotos.store'), ['archivo' => $copia], ['Accept' => 'application/json'])
            ->assertStatus(409)->assertJsonPath('message', 'Esta fotografía podría ya formar parte del archivo.');
        $this->post(route('archivo.gestion.fotos.store'), ['archivo' => $copia, 'confirmar_duplicado' => 1], ['Accept' => 'application/json'])->assertCreated();
        $this->assertSame(2, ArchivoFoto::query()->count(), 'nunca se borra automáticamente');
    }

    public function test_rechaza_archivos_que_no_son_imagen(): void
    {
        $this->actingAs($this->usuario());
        $this->post(route('archivo.aportes.subir'), ['archivo' => UploadedFile::fake()->create('doc.pdf', 20, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('archivo');
    }

    public function test_eliminar_borra_original_derivados_y_portadas(): void
    {
        $this->actingAs($this->admin());
        $foto = $this->subirComoEquipo();
        $a = ArchivoAcontecimiento::query()->create(['titulo' => 'X', 'slug' => 'x', 'anio' => 2000, 'portada_foto_id' => $foto->id]);
        $derivado = $foto->derivados['400']['path'];

        $this->delete(route('archivo.gestion.fotos.destroy', $foto))->assertRedirect(route('archivo.gestion.fotos'));
        Storage::disk('comprobantes')->assertMissing($foto->path);
        Storage::disk('comprobantes')->assertMissing($derivado);
        $this->assertNull($a->fresh()->portada_foto_id);
    }

    public function test_reemplazar_imagen_conserva_los_datos(): void
    {
        $this->actingAs($this->admin());
        $foto = $this->subirComoEquipo();
        $this->put(route('archivo.gestion.fotos.update', $foto), ['titulo' => 'Fiesta Chilinga', 'anio' => 2005])->assertRedirect();
        $anterior = $foto->fresh()->path;

        $this->post(route('archivo.gestion.fotos.imagen', $foto), ['archivo' => $this->imagen('nueva.png', 900, 1400)])->assertRedirect();
        $foto->refresh();
        $this->assertSame('Fiesta Chilinga', $foto->titulo);
        $this->assertSame([900, 1400], [$foto->ancho, $foto->alto]);
        $this->assertTrue($foto->esVertical());
        Storage::disk('comprobantes')->assertMissing($anterior);
    }

    public function test_fusionar_personas_mueve_las_apariciones(): void
    {
        $a = $this->persona('Dani');
        $b = $this->persona('Daniel');
        $foto = ArchivoFoto::query()->create(['slug' => 'f', 'path' => 'x.jpg', 'estado' => 'publicada']);
        ArchivoFotoPersona::query()->create(['archivo_foto_id' => $foto->id, 'persona_id' => $b->id]);

        app(PersonaService::class)->fusionar($a, $b);
        $this->assertSame($a->id, $foto->personas()->value('persona_id'));
    }

    public function test_portada_vacia_invita_a_aportar(): void
    {
        $this->get(route('archivo.index'))->assertOk()->assertSee('Todavía no hay historia publicada')->assertSee(route('archivo.aportar'));
        $this->get(route('archivo.aportar'))->assertRedirect(route('login'));
    }
}
