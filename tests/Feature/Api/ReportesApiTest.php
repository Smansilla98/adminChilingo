<?php

namespace Tests\Feature\Api;

use App\Models\Cuota;
use App\Models\Gasto;
use App\Models\Pago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

class ReportesApiTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function escenario(): array
    {
        $palomar = $this->sede('Palomar');
        $varela = $this->sede('Varela');
        $bloque = $this->bloque($palomar);
        $profe = $this->asignarDocente($this->persona('Juana'), $bloque);
        $ana = $this->inscribirAlumno($this->persona('Ana'), $bloque);
        $cuota = Cuota::query()->create(['nombre' => 'Agosto', 'año' => 2026, 'mes' => 8, 'monto' => 20000, 'alcance' => 'bloque', 'bloque_id' => $bloque->id, 'activo' => true]);
        $pago = Pago::query()->create(['fecha_pago' => '2026-08-05', 'monto_total' => 20000]);
        $pago->detalles()->create(['alumno_id' => $ana->id, 'cuota_id' => $cuota->id, 'monto' => 20000]);
        Gasto::query()->forceCreate(['sede_id' => $palomar->id, 'fecha' => '2026-08-10', 'tipo' => 'alquiler', 'monto' => 5000, 'estado' => 'aprobado']);
        Gasto::query()->forceCreate(['sede_id' => $varela->id, 'fecha' => '2026-08-10', 'tipo' => 'alquiler', 'monto' => 7000, 'estado' => 'aprobado']);

        return compact('palomar', 'varela', 'profe');
    }

    public function test_reporte_global_con_datos_y_exportaciones(): void
    {
        $this->escenario();
        $contador = $this->usuario('Contador');
        $this->asignarRol($contador->persona, 'contador');
        Sanctum::actingAs($contador->fresh());

        $r = $this->getJson('/api/v1/reportes?mes=8&anio=2026')->assertOk();
        $this->assertSame('global', $r->json('alcance'));
        $sedes = collect($r->json('financiero_sede'))->keyBy('sede.nombre');
        $this->assertEquals(5000, $sedes['Palomar']['gastos']);
        $this->assertEquals(7000, $sedes['Varela']['gastos']);
        $this->assertSame('Juana', $r->json('ingresos_profesor.0.profesor.nombre'));

        $this->get('/api/v1/reportes/excel?mes=8&anio=2026')->assertOk()->assertDownload('reportes-2026-08.xlsx');
        $html = $this->getJson('/api/v1/reportes/imprimible?mes=8&anio=2026')->assertOk();
        $this->assertSame('reportes-2026-08.pdf', $html->json('nombre'));
        $this->assertStringContainsString('Palomar', $html->json('html'));
        $this->getJson('/api/v1/reportes/profesores')->assertOk()->assertJsonPath('data.0.profesor.nombre', 'Juana');
        $this->getJson('/api/v1/reportes?mes=13')->assertStatus(422);
    }

    public function test_coordinador_ve_solo_su_sede_y_sin_permiso_es_403(): void
    {
        ['palomar' => $palomar] = $this->escenario();
        $coord = $this->usuario('Coord');
        $this->asignarRol($coord->persona, 'coordinador', 'sede', $palomar);
        Sanctum::actingAs($coord->fresh());

        $r = $this->getJson('/api/v1/reportes?mes=8&anio=2026')->assertOk();
        $this->assertSame('sedes', $r->json('alcance'));
        $this->assertSame(['Palomar'], collect($r->json('financiero_sede'))->pluck('sede.nombre')->all());

        $profe = $this->usuario('Profe');
        $this->asignarDocente($profe->persona, $this->bloque($palomar, 'Otro'));
        Sanctum::actingAs($profe->fresh());
        $this->getJson('/api/v1/reportes')->assertForbidden();
        $this->get('/api/v1/reportes/excel')->assertForbidden();
    }
}
