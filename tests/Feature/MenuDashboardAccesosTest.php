<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenarios;
use Tests\TestCase;

/** El menú lateral muestra lo destacado; el inicio muestra todos los accesos por sección. Ningún acceso se pierde. */
class MenuDashboardAccesosTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    /** @return string HTML del menú lateral */
    private function menuLateral(string $html): string
    {
        $inicio = strpos($html, 'class="side-nav"');
        $fin = strpos($html, '</nav>', (int) $inicio);

        return $inicio === false || $fin === false ? '' : substr($html, $inicio, $fin - $inicio);
    }

    public function test_el_menu_lateral_muestra_destacados_y_el_inicio_muestra_todo(): void
    {
        $admin = $this->admin('Dirección');

        $html = $this->actingAs($admin)->get(route('dashboard'))->assertOk()->getContent();

        $menu = $this->menuLateral($html);
        $this->assertNotSame('', $menu, 'no encontré el menú lateral');
        $this->assertStringContainsString('Cuotas', $menu);
        $this->assertStringNotContainsString('Órdenes de compra', $menu, 'lo secundario no debe ocupar el menú lateral');

        $this->assertStringContainsString('Accesos por sección', $html);
        $this->assertStringContainsString('Órdenes de compra', $html, 'el inicio debe mostrar todos los accesos');
        $this->assertStringContainsString('Mensajes a grupos', $html);
    }
}
