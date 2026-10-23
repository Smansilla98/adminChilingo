<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Escenarios;
use Tests\TestCase;

class EstadoSistemaTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_solo_administracion_ve_el_estado_del_sistema(): void
    {
        $this->actingAs($this->usuario('Sin permisos'))->get('/estado-sistema')->assertForbidden();
        $this->actingAs($this->admin())->get('/estado-sistema')
            ->assertOk()
            ->assertSee('Dirección del sistema (APP_URL)')
            ->assertSee('Pago de cuotas por transferencia');
    }

    public function test_en_produccion_avisa_el_dominio_viejo_y_los_archivos_que_se_borran(): void
    {
        Storage::fake('comprobantes');
        $this->app['env'] = 'production';
        config(['app.url' => 'https://web-production-a4f7e.up.railway.app']);

        $this->actingAs($this->admin())->get('http://admin-chilingo.test/estado-sistema')
            ->assertOk()
            ->assertSee('apuntan a «web-production-a4f7e.up.railway.app»', false)
            ->assertSee('APP_URL a https://admin-chilingo.test')
            ->assertSee('Railway los borra en cada deploy')
            ->assertSee('2 problemas necesitan arreglo');
    }

    public function test_el_inicio_avisa_las_fallas_de_configuracion(): void
    {
        Storage::fake('comprobantes');
        $this->app['env'] = 'production';
        config(['app.url' => 'https://otro-dominio.example']);

        $this->actingAs($this->admin())->get('http://admin-chilingo.test/dashboard')
            ->assertOk()
            ->assertSee('fallas de configuración del sistema');
    }
}
