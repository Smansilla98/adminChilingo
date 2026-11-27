<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenarios;
use Tests\TestCase;

class DashboardSedeSelectorTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_el_inicio_ofrece_selector_de_sede_con_varias_sedes(): void
    {
        $admin = $this->admin('Santi');
        $palomar = $this->sede('Palomar');
        $this->sede('Saavedra');

        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertSee('Todas las sedes')
            ->assertSee('Palomar');
    }

    public function test_elegir_una_sede_filtra_el_encabezado(): void
    {
        $admin = $this->admin('Santi');
        $palomar = $this->sede('Palomar');
        $this->sede('Saavedra');

        $this->actingAs($admin)->get('/dashboard?sede='.$palomar->id)
            ->assertOk()
            ->assertSee('Sede Palomar')
            ->assertSee('value="'.$palomar->id.'" selected', false);
    }

    public function test_un_id_de_sede_inexistente_se_ignora(): void
    {
        $admin = $this->admin('Santi');
        $this->sede('Palomar');

        $this->actingAs($admin)->get('/dashboard?sede=99999')
            ->assertOk()
            ->assertDontSee('selected="selected"', false)
            ->assertDontSee('Sede 99999');
    }
}
