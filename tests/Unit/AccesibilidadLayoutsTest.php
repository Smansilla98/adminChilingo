<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AccesibilidadLayoutsTest extends TestCase
{
    public function test_layouts_tienen_skip_link_y_lang_es(): void
    {
        $base = dirname(__DIR__, 2).'/resources/views/layouts';
        foreach (['app.blade.php', 'guest.blade.php', 'admin.blade.php', 'publico.blade.php'] as $file) {
            $html = file_get_contents($base.'/'.$file);
            $this->assertNotFalse($html, $file);
            $this->assertStringContainsString('lang="es"', $html, $file);
            $this->assertStringContainsString('ito-skip', $html, $file);
            $this->assertStringContainsString('contenido-principal', $html, $file);
        }
    }

    public function test_css_respeta_reduced_motion(): void
    {
        $css = file_get_contents(dirname(__DIR__, 2).'/public/css/chilinga-admin.css');
        $this->assertStringContainsString('prefers-reduced-motion', $css);
        $this->assertStringContainsString('.ito-skip', $css);
    }
}
