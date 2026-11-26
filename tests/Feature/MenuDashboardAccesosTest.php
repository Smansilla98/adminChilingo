<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenarios;
use Tests\TestCase;

/** El menú lateral muestra lo destacado. El inicio agrupa el resto en módulos, sin perder accesos. */
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

        $this->assertStringContainsString('Módulos', $html);
        $this->assertStringContainsString('Inventario y compras', $html);
        $this->assertStringContainsString('Órdenes de compra', $html, 'el panel del módulo debe conservar los accesos que no están en el menú');
        $this->assertStringContainsString('Mensajes a grupos', $html);
    }
}
