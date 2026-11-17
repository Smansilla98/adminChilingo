<?php

namespace Tests\Feature\Api;

use App\Domain\Personas\CicloLectivoService;
use App\Models\CicloLectivo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

/** El calendario del ciclo es de lectura para cualquier cuenta; crear o abrir ciclos sigue con permiso. */
class CalendarioCicloApiTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function ciclo(): CicloLectivo
    {
        return app(CicloLectivoService::class)->abrir([
            'anio' => 2027, 'nombre' => null, 'inicio' => '2027-03-01', 'fin' => '2027-12-15',
        ], $this->admin());
    }

    public function test_un_alumno_sin_permisos_de_administracion_ve_el_calendario(): void
    {
        $ciclo = $this->ciclo();
        $alumno = $this->usuario('Alumna sin permisos');
        Sanctum::actingAs($alumno->fresh());

        $this->getJson('/api/v1/ciclos-lectivos')->assertOk()->assertJsonPath('data.0.anio', 2027);
        $r = $this->getJson("/api/v1/ciclos-lectivos/{$ciclo->id}/calendario")->assertOk();
        $this->assertNotEmpty($r->json('data'));
    }

    public function test_un_alumno_sin_permisos_no_puede_crear_ni_abrir_un_ciclo(): void
    {
        $alumno = $this->usuario('Alumna sin permisos');
        Sanctum::actingAs($alumno->fresh());

        $this->postJson('/api/v1/ciclos-lectivos', ['anio' => 2028, 'inicio' => '2028-03-01', 'fin' => '2028-12-15'])->assertForbidden();
        $this->assertDatabaseMissing('ciclos_lectivos', ['anio' => 2028]);
    }

    public function test_el_calendario_exige_sesion(): void
    {
        $ciclo = $this->ciclo();
        $this->getJson("/api/v1/ciclos-lectivos/{$ciclo->id}/calendario")->assertUnauthorized();
    }
}
