<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

class DireccionApiTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_tablero_de_direccion_por_api_con_permiso_y_sin_el(): void
    {
        $palomar = $this->sede('Palomar');
        $this->bloque($palomar, 'Bloque P');
        $dir = $this->usuario('Dirección');
        $this->asignarPermiso($dir->persona, 'reportes.direccion');
        Sanctum::actingAs($dir->fresh());

        $this->getJson('/api/v1/direccion')->assertOk()
            ->assertJsonPath('alcance', 'global')
            ->assertJsonCount(12, 'serie')
            ->assertJsonStructure(['actual', 'totales_12m', 'alumnos', 'morosidad', 'ocupacion', 'asistencia_promedio', 'proyeccion', 'sedes']);

        $sinPermiso = $this->usuario('Alumno');
        Sanctum::actingAs($sinPermiso->fresh());
        $this->getJson('/api/v1/direccion')->assertForbidden();
    }
}
