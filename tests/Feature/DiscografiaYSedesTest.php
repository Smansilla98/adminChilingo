<?php

namespace Tests\Feature;

use App\Http\Controllers\DiscografiaController;
use App\Models\Auditoria;
use App\Models\Disco;
use App\Models\ProgramaRitmo;
use App\Models\Sede;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Escenarios;
use Tests\TestCase;

class DiscografiaYSedesTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_la_discografia_lista_los_cinco_discos_en_orden(): void
    {
        $this->assertSame(
            ['Percusión', 'Viejos Dioses', 'Muñequitos del tambor', 'Raíces', 'Banda Fantasma'],
            Disco::query()->publicados()->pluck('titulo')->all()
        );

        $this->get(route('programa.discos.index'))
            ->assertOk()
            ->assertSeeInOrder(['Percusión', '1998', 'Viejos Dioses', '2001', 'Muñequitos del tambor', '2004', 'Raíces', '2007', 'Banda Fantasma', '2010'])
            ->assertSee('31:00');
    }

    public function test_la_ficha_del_disco_muestra_temas_fuentes_y_toques_del_programa(): void
    {
        $this->get(route('programa.discos.show', 'percusion'))
            ->assertOk()
            ->assertSeeInOrder(['Intro', '2:51', 'Sacateca', '1:53', 'Toque a Eleggua', '2:12'])
            ->assertSee('31:00 en total')
            ->assertSee('Toque del programa: Sacateca')
            ->assertSee('Toque del programa: Malamakua I')
            ->assertSee('https://www.tagtuner.com/music/albums/La-Chilinga/Percusion/album-v292ab7', false)
            ->assertSee('Buscar en Spotify');

        // Lo que no está confirmado se dice.
        $this->get(route('programa.discos.show', 'banda-fantasma'))
            ->assertOk()
            ->assertSee('También aparece como')
            ->assertSee('falta confirmarla con la escuela');
    }

    public function test_la_discografia_queda_corregida_con_fuentes(): void
    {
        $this->assertNull(Disco::query()->where('slug', 'percusion')->value('nota_anio'));
        $raices = Disco::query()->where('slug', 'raices')->sole();
        $this->assertContains('Grabado por más de 250 alumnos', $raices->datos);
        $this->assertSame('Ciudad.com — ¡Tambores a la calle! (22/08/2007)', $raices->fuentes[0]['etiqueta']);

        $this->get(route('programa.discos.show', 'percusion'))->assertOk()->assertDontSee('2003');
        $this->get(route('programa.discos.show', 'viejos-dioses'))->assertOk()->assertSee('Jaime Roos');
    }

    public function test_la_correccion_no_pisa_lo_que_edito_la_escuela(): void
    {
        $migracion = require database_path('migrations/2026_10_08_000001_corregir_discografia_con_fuentes.php');
        Disco::query()->where('slug', 'raices')->update(['descripcion' => 'Texto de la escuela']);

        $migracion->down();
        $migracion->up();

        $this->assertSame('Texto de la escuela', Disco::query()->where('slug', 'raices')->value('descripcion'));
        // Lo que nadie tocó vuelve a quedar corregido.
        $this->assertNull(Disco::query()->where('slug', 'percusion')->value('nota_anio'));
    }

    public function test_un_tema_numerado_no_se_cruza_con_otro_numero(): void
    {
        $disco = Disco::query()->where('slug', 'munequitos-del-tambor')->sole();
        $toques = ProgramaRitmo::query()->get();
        $cruces = collect($disco->toquesDelPrograma($toques))->map(fn ($t) => $t->nombre);

        $this->assertSame('Muñequitos I', $cruces[1] ?? null);
        $this->assertArrayNotHasKey(14, $cruces->all(), 'Muñequitos II no es el toque Muñequitos I');
    }

    public function test_un_toque_fuera_del_programa_no_se_enlaza_desde_el_disco(): void
    {
        ProgramaRitmo::query()->where('nombre', 'Sacateca')->update(['en_programa' => false]);

        // Tampoco se cruza con «Sacateca II», que es otro toque.
        $this->get(route('programa.discos.show', 'percusion'))->assertOk()->assertDontSee('Toque del programa: Sacateca');
    }

    public function test_un_disco_sin_publicar_no_es_publico(): void
    {
        Disco::query()->where('slug', 'raices')->update(['publicado' => false]);

        $this->get(route('programa.discos.show', 'raices'))->assertNotFound();
        $this->get(route('programa.discos.index'))->assertOk()->assertDontSee('Raíces');
        $this->actingAs($this->admin())->get(route('programa.discos.show', 'raices'))->assertOk()->assertSee('no está publicado');
    }

    public function test_administracion_edita_el_disco_con_portada(): void
    {
        Storage::fake('comprobantes');
        $disco = Disco::query()->where('slug', 'munequitos-del-tambor')->sole();

        $persona = $this->persona('Profe');
        $profe = $this->usuario('Profe', $persona);
        $this->asignarPermiso($persona, 'partituras.admin');
        $this->actingAs($profe)->get(route('programa.discos.edit', $disco))->assertForbidden();

        $this->actingAs($this->admin());
        $this->get(route('programa.discos.edit', $disco))->assertOk()->assertSee('Va llegando');

        $this->put(route('programa.discos.update', $disco), [
            'titulo' => 'Muñequitos del tambor',
            'anio' => 2004,
            'color' => '#047857',
            'temas' => "1. Va llegando — 3:10\nMuñequitos I 4:05\nBará",
            'enlaces' => 'Spotify | https://open.spotify.com/album/abc',
            'portada' => UploadedFile::fake()->image('tapa.jpg', 600, 600),
            'publicado' => '1',
        ])->assertRedirect(route('programa.discos.show', $disco));

        $disco->refresh();
        $this->assertSame([
            ['titulo' => 'Va llegando', 'duracion' => '3:10'],
            ['titulo' => 'Muñequitos I', 'duracion' => '4:05'],
            ['titulo' => 'Bará', 'duracion' => null],
        ], $disco->temas);
        $this->assertNull($disco->duracionTotal(), 'Falta una duración: no hay total');
        Storage::disk('comprobantes')->assertExists($disco->portada_path);
        $this->get(route('programa.discos.portada', $disco))->assertOk();
        $this->assertTrue(Auditoria::query()->where('accion', 'discografia.disco.editado')->where('entidad_id', $disco->id)->exists());

        // Spotify ya está cargado: no se ofrece buscarlo, YouTube sí.
        $this->get(route('programa.discos.show', $disco))->assertSee('https://open.spotify.com/album/abc', false)
            ->assertDontSee('Buscar en Spotify')->assertSee('Buscar en YouTube');
    }

    public function test_los_enlaces_tienen_que_ser_urls(): void
    {
        $disco = Disco::query()->where('slug', 'raices')->sole();
        $this->actingAs($this->admin());

        $this->put(route('programa.discos.update', $disco), [
            'titulo' => 'Raíces', 'anio' => 2007, 'color' => '#7c2d12',
            'enlaces' => 'Spotify | javascript:alert(1)',
        ])->assertSessionHasErrors('enlaces');

        $this->assertSame('Apple Music', $disco->fresh()->enlaces[0]['etiqueta']);
    }

    public function test_lectura_de_temas_tolera_formatos_comunes(): void
    {
        $this->assertSame([
            ['titulo' => 'Sacateca', 'duracion' => '1:53'],
            ['titulo' => 'Toque en 7', 'duracion' => null],
            ['titulo' => 'Línea 4, 5, 7', 'duracion' => null],
            ['titulo' => 'Uh-ah', 'duracion' => '3:31'],
        ], DiscografiaController::temas("Sacateca - 1:53\n\n2) Toque en 7\nLínea 4, 5, 7\nUh-ah — 3:31"));
    }

    public function test_el_mapa_publica_solo_sedes_activas_y_sin_datos_internos(): void
    {
        Sede::query()->create(['nombre' => 'Quilmes', 'direccion' => 'Humberto Primo 320, Quilmes', 'latitud' => -34.7231682, 'longitud' => -58.2534036, 'en_mapa' => true, 'activo' => true, 'costo_alquiler_mensual' => 987654]);
        Sede::query()->create(['nombre' => 'Palomar', 'direccion' => 'Ing. Marconi 181, El Palomar', 'en_mapa' => true, 'activo' => true]);
        Sede::query()->create(['nombre' => 'Sede interna', 'direccion' => 'Oculta 1', 'en_mapa' => false, 'activo' => true]);
        Sede::query()->create(['nombre' => 'Sede cerrada', 'direccion' => 'Cerrada 1', 'latitud' => -34.6, 'longitud' => -58.4, 'en_mapa' => true, 'activo' => false]);

        $r = $this->get(route('programa.sedes'))->assertOk()
            ->assertSee('data-mapa-sedes', false)
            ->assertSee('Quilmes')
            ->assertSee('Palomar')
            ->assertSee('Todavía no está ubicada en el mapa.')
            ->assertSee('https://www.google.com/maps/search/?api=1&amp;query=Ing.%20Marconi%20181', false)
            ->assertDontSee('Sede interna')
            ->assertDontSee('Sede cerrada')
            ->assertDontSee('987654');
        $this->assertStringContainsString('-34.7231682', $r->getContent());

        // El programa muestra el mapa en su sección de sedes y las tapas en la de discos.
        $this->get(route('programa.index'))->assertOk()
            ->assertSee('data-mapa-sedes', false)
            ->assertSee(route('programa.sedes'), false)
            ->assertSee(route('programa.discos.show', 'percusion'), false)
            ->assertDontSee('<li><strong>Saavedra</strong> — Ruiz Huidobro 4228</li>', false);
    }

    public function test_administracion_ubica_la_sede_desde_su_ficha(): void
    {
        $sede = Sede::query()->create(['nombre' => 'Varela', 'direccion' => 'Cerro Aconcagua 2153, Florencio Varela', 'activo' => true]);
        $this->actingAs($this->admin());

        $this->get(route('sedes.edit', $sede))->assertOk()->assertSee('data-mapa-picker', false);

        $this->put(route('sedes.update', $sede), [
            'nombre' => 'Varela', 'direccion' => $sede->direccion, 'activo' => '1',
            'latitud' => '-34.7900000', 'en_mapa' => '1',
        ])->assertSessionHasErrors('longitud');

        $this->put(route('sedes.update', $sede), [
            'nombre' => 'Varela', 'direccion' => $sede->direccion, 'activo' => '1',
            'latitud' => '-34.7900000', 'longitud' => '-58.2750000', 'en_mapa' => '1',
        ])->assertSessionHasNoErrors();

        $sede->refresh();
        $this->assertTrue($sede->en_mapa);
        $this->assertEqualsWithDelta(-34.79, $sede->latitud, 0.000001);
        $this->assertStringStartsWith('https://www.google.com/maps/dir/?api=1&destination=-34.79', $sede->urlComoLlegar());
    }
}
