<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenarios;
use Tests\TestCase;

class RedireccionBloqueAsistenciaTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_el_enlace_de_bloque_lleva_al_alta_de_asistencia_con_el_bloque(): void
    {
        $bloque = $this->bloque($this->sede('Palomar'));

        $this->actingAs($this->admin())
            ->get('/asistencias/bloque/'.$bloque->id)
            ->assertRedirect('/asistencias/create?bloque_id='.$bloque->id);
    }
}
