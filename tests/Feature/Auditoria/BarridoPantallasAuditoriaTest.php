<?php

namespace Tests\Feature\Auditoria;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\Escenarios;
use Tests\TestCase;

/**
 * Barrido de pantallas: toda ruta web GET sin parámetros responde sin error de servidor (5xx).
 * Con base vacía: los estados vacíos tienen que renderizar.
 */
class BarridoPantallasAuditoriaTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_ninguna_pantalla_get_sin_parametros_responde_5xx_con_base_vacia(): void
    {
        $admin = $this->admin('Barrido');
        $fallos = [];
        $probadas = 0;

        foreach (Route::getRoutes() as $ruta) {
            if (! in_array('GET', $ruta->methods(), true) || str_contains($ruta->uri(), '{') || ! $ruta->getName()) {
                continue;
            }
            if (in_array('api', $ruta->gatherMiddleware(), true) || str_starts_with($ruta->uri(), 'api/')) {
                continue;
            }
            $probadas++;
            $status = $this->actingAs($admin)->get('/'.ltrim($ruta->uri(), '/'))->getStatusCode();
            if ($status >= 500) {
                $fallos[] = $status.' '.$ruta->uri().' ('.$ruta->getName().')';
            }
        }

        $this->assertGreaterThan(50, $probadas, 'El barrido tiene que recorrer las pantallas');
        $this->assertSame([], $fallos, 'Pantallas con error de servidor');
    }
}
