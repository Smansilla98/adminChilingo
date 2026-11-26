<?php

namespace Tests\Feature;

use App\Domain\Biblioteca\CatalogoBiblioteca;
use App\Models\BibliotecaItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenarios;
use Tests\TestCase;

/**
 * Regresión del catálogo público de la Biblioteca: página /biblioteca con filtros
 * y catálogo de imágenes para el editor de diseño (consumido por CatalogoBiblioteca).
 */
class BibliotecaCatalogoTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function items(): array
    {
        $imagen = BibliotecaItem::query()->create([
            'titulo' => 'Corso rojo', 'tipo' => 'imagen', 'path' => 'biblioteca/corso.png', 'mime' => 'image/png', 'estado' => 'publicado',
        ]);
        $enlace = BibliotecaItem::query()->create([
            'titulo' => 'Video del corso', 'tipo' => 'enlace', 'url' => 'https://example.com/v', 'estado' => 'publicado',
        ]);
        $oculto = BibliotecaItem::query()->create([
            'titulo' => 'Corso oculto', 'tipo' => 'imagen', 'path' => 'biblioteca/oculto.png', 'mime' => 'image/png', 'estado' => 'oculto',
        ]);

        return [$imagen, $enlace, $oculto];
    }

    public function test_la_pagina_publica_solo_muestra_publicados_y_filtra(): void
    {
        [$imagen, $enlace] = $this->items();

        $this->get(route('biblioteca.index'))->assertOk()
            ->assertViewHas('sinTabla', false)
            ->assertViewHas('items', fn ($p) => $p->total() === 2);

        $this->get(route('biblioteca.index', ['q' => 'corso']))->assertOk()
            ->assertViewHas('items', fn ($p) => $p->total() === 2);

        $this->get(route('biblioteca.index', ['q' => 'rojo']))->assertOk()
            ->assertViewHas('items', fn ($p) => $p->total() === 1 && $p->items()[0]->id === $imagen->id)
            ->assertViewHas('q', 'rojo');

        $this->get(route('biblioteca.index', ['tipo' => 'enlace']))->assertOk()
            ->assertViewHas('items', fn ($p) => $p->total() === 1 && $p->items()[0]->id === $enlace->id);
    }

    public function test_el_catalogo_para_diseno_devuelve_solo_imagenes_publicadas(): void
    {
        [$imagen] = $this->items();
        $catalogo = app(CatalogoBiblioteca::class);

        $respuesta = $catalogo->catalogoDiseno([]);
        $this->assertTrue($respuesta['ok']);
        $this->assertSame(1, $respuesta['meta']['total']);
        $this->assertSame($imagen->id, $respuesta['data'][0]['id']);
        $this->assertSame('Corso rojo', $respuesta['data'][0]['titulo']);

        $this->assertSame(0, $catalogo->catalogoDiseno(['q' => 'nada'])['meta']['total']);
        $this->assertSame(1, $catalogo->catalogoDiseno(['tipo' => 'enlace'])['meta']['total']);
    }
}
