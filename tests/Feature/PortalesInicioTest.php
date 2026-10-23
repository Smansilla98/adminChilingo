<?php

namespace Tests\Feature;

use App\Models\Cuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenarios;
use Tests\TestCase;

/** Lo que ven en el inicio el alumno y el docente. */
class PortalesInicioTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_el_alumno_ve_tambien_las_cuotas_de_meses_anteriores(): void
    {
        $persona = $this->persona('Juana');
        $user = $this->usuario('Juana', $persona);
        $bloque = $this->bloque($this->sede('Palomar'));
        $this->inscribirAlumno($persona, $bloque);
        foreach ([now()->subMonth(), now()] as $mes) {
            Cuota::query()->create(['bloque_id' => $bloque->id, 'alcance' => Cuota::ALCANCE_BLOQUE, 'nombre' => 'Cuota '.$mes->format('m/Y'), 'año' => $mes->year, 'mes' => $mes->month, 'monto' => 20000, 'activo' => true]);
        }

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Debés 2 cuotas en total')
            ->assertSee('$40.000 contando meses anteriores', false)
            ->assertSee('Enviar comprobante');

        config(['chilinga.cobro.alias' => 'cifra.jardin.pez']);
        $this->actingAs($user)->get('/dashboard')
            ->assertSee('Pagar todo junto')
            ->assertSee(route('alumno.cuota.pagar'), false);
    }

    public function test_el_docente_sin_clase_hoy_no_ve_asistencia_cargada(): void
    {
        $persona = $this->persona('Rodri');
        $user = $this->usuario('Rodri', $persona);
        $this->asignarDocente($persona, $this->bloque($this->sede('Palomar')));

        $this->actingAs($user->fresh())->get('/dashboard')
            ->assertOk()
            ->assertSee('Hoy no tenés clase')
            ->assertDontSee('Ya está cargada');
    }
}
