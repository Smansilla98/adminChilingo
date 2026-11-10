<?php

namespace Tests\Feature;

use App\Models\Cuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenarios;
use Tests\TestCase;

/** Formulario público de comprobantes: sin cuenta no se revela el nombre del alumno. */
class ComprobantePublicoTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    /** @return array<string, mixed> */
    private function busqueda(): array
    {
        $sede = $this->sede('Sede Pública');
        $bloque = $this->bloque($sede, 'Bloque Público');
        Cuota::query()->create(['bloque_id' => $bloque->id, 'alcance' => Cuota::ALCANCE_BLOQUE, 'nombre' => 'Marzo', 'año' => 2026, 'mes' => 3, 'monto' => 1000, 'activo' => true]);
        $persona = $this->persona('Juana', '30111222');
        $persona->update(['apellido' => 'Pérez']);
        $alumno = $this->inscribirAlumno($persona->fresh(), $bloque);
        $alumno->update(['dni' => '30111222', 'nombre_apellido' => 'Juana Pérez']);

        return ['sede_id' => $sede->id, 'año' => 2026, 'mes' => 3, 'bloque_ids' => [$bloque->id], 'dni' => '30.111.222'];
    }

    public function test_la_busqueda_por_dni_solo_muestra_iniciales(): void
    {
        $q = $this->busqueda();

        $this->getJson(route('comprobante-cuota-public.api.alumnos', $q))->assertOk()
            ->assertJsonPath('alumnos.0.nombre_apellido', 'J*** P***')
            ->assertDontSee('Juana');
    }

    public function test_un_robot_que_completa_la_trampa_no_obtiene_datos(): void
    {
        $q = $this->busqueda() + ['sitio_web' => 'http://spam.example'];

        $this->getJson(route('comprobante-cuota-public.api.alumnos', $q))->assertOk()->assertJsonCount(0, 'alumnos');
    }

    public function test_la_busqueda_tiene_limite_estricto(): void
    {
        $q = $this->busqueda();
        for ($i = 0; $i < 5; $i++) {
            $this->getJson(route('comprobante-cuota-public.api.alumnos', $q))->assertOk();
        }
        $this->getJson(route('comprobante-cuota-public.api.alumnos', $q))->assertStatus(429);
    }

    public function test_el_formulario_se_puede_cerrar(): void
    {
        config(['chilinga.formulario_publico_comprobantes' => false]);
        $q = $this->busqueda();

        $this->get(route('comprobante-cuota-public.create'))->assertNotFound()->assertSee('Entrá con tu cuenta');
        $this->getJson(route('comprobante-cuota-public.api.alumnos', $q))->assertNotFound();
        $this->post(route('comprobante-cuota-public.store'), [])->assertNotFound();
    }

    public function test_enmascarar(): void
    {
        $this->assertSame('J*** P***', \App\Http\Controllers\ComprobanteCuotaAlumnoPublicController::enmascarar('juan  pérez'));
        $this->assertSame('Á*** D*** L***', \App\Http\Controllers\ComprobanteCuotaAlumnoPublicController::enmascarar('Ángela de la'));
    }
}
