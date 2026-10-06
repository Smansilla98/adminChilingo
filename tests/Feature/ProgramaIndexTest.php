<?php

namespace Tests\Feature;

use App\Models\ProgramaRitmo;
use App\Models\ProgramaSeccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramaIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_contenido_de_la_escuela_va_arriba_y_los_toques_abajo(): void
    {
        ProgramaSeccion::query()->create([
            'slug' => 'objetivos-primer-anio', 'titulo' => 'Objetivos de primer año',
            'cuerpo' => '<p>Tocar en bloque.</p>', 'orden' => 1,
            'categoria' => ProgramaSeccion::CAT_ANIO, 'anio' => 1, 'activo' => true,
        ]);
        ProgramaRitmo::query()->create([
            'slug' => 'toque-de-prueba', 'año' => 1, 'orden' => 1,
            'nombre' => 'Toque de prueba', 'publicado' => true,
        ]);

        $this->get(route('programa.index'))
            ->assertOk()
            ->assertSeeInOrder(['href="#contenido-programa"', 'href="#toques-por-anio"'], false)
            ->assertSeeInOrder(['id="contenido-programa"', 'Objetivos de primer año', 'id="toques-por-anio"', 'Toque de prueba'], false);
    }
}
