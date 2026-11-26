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

        // El inicio muestra las tarjetas de módulos; cada tarjeta lleva a su página.
        $this->assertStringContainsString('Módulos', $html);
        $this->assertStringContainsString('Inventario y compras', $html);
        $this->assertStringContainsString(route('modulos.show', 'inventario'), $html);
        $this->assertStringNotContainsString('offcanvas', $html, 'sin paneles superpuestos');

        // La página del módulo conserva los accesos que no están en el menú.
        $inventario = $this->actingAs($admin)->get(route('modulos.show', 'inventario'))->assertOk()->getContent();
        $this->assertStringContainsString('Órdenes de compra', $inventario);
        $actividades = $this->actingAs($admin)->get(route('modulos.show', 'actividades'))->assertOk()->getContent();
        $this->assertStringContainsString('Mensajes a grupos', $actividades);

        // Un módulo que no existe o que el usuario no ve responde 404.
        $this->actingAs($admin)->get('/modulos/no-existe')->assertNotFound();
    }
}
