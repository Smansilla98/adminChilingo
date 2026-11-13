<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Al compartir un enlace público aparece la imagen de la escuela (WhatsApp, Instagram, Facebook). */
class MiniaturasCompartirTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_programa_publico_trae_imagen_para_compartir(): void
    {
        $html = $this->get(route('programa.index'))->assertOk()->getContent();

        $this->assertStringContainsString('property="og:image"', $html);
        $this->assertStringContainsString('images/brand/og-chilinga.png', $html);
        $this->assertStringContainsString('property="og:title"', $html);
        $this->assertStringContainsString('summary_large_image', $html);
    }

    public function test_la_imagen_por_defecto_existe_y_tiene_el_tamano_de_las_redes(): void
    {
        $ruta = public_path('images/brand/og-chilinga.png');
        $this->assertFileExists($ruta);
        $this->assertSame([1200, 630], array_slice(getimagesize($ruta), 0, 2));
    }

    public function test_la_biblioteca_no_repite_las_etiquetas_de_la_pagina_por_defecto(): void
    {
        $item = \App\Models\BibliotecaItem::query()->create([
            'titulo' => 'Toque de prueba', 'tipo' => 'enlace', 'url' => 'https://example.com', 'estado' => 'publicado',
        ]);

        $html = $this->get(route('biblioteca.show', $item))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'property="og:title"'));
        $this->assertSame(1, substr_count($html, 'property="og:image"'));
    }
}
