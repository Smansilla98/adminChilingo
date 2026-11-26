<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenarios;
use Tests\TestCase;

/**
 * Regresión del buscador de personas para etiquetar fotos del archivo (gestión).
 */
class ArchivoBuscarPersonasTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_busca_personas_del_sistema_y_exige_dos_letras(): void
    {
        $this->persona('Daniel Gomez', '30555666');
        $this->persona('Lucia Ferrer', '30555777');
        $admin = $this->admin();

        $this->actingAs($admin)->getJson(route('archivo.gestion.personas.buscar', ['q' => 'D']))
            ->assertOk()->assertExactJson(['data' => []]);

        $this->actingAs($admin)->getJson(route('archivo.gestion.personas.buscar', ['q' => 'Daniel']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Daniel Gomez')
            ->assertJsonStructure(['data' => [['persona_id', 'nombre']]]);
    }
}
