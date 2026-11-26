<?php

namespace Tests\Feature\Auditoria;

use App\Domain\Operativo\CierreMesService;
use App\Models\Asistencia;
use App\Models\ComprobanteCuotaAlumno;
use App\Models\Cuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Escenarios;
use Tests\TestCase;

/**
 * Auditoría del cierre de mes (octubre 2026). Escenario y cifras esperadas a mano:
 *  - 3 bloques activos. Asistencia en octubre en dos (Lunes y Martes). El Jueves solo tiene una de septiembre,
 *    que no cuenta → 1 bloque sin asistencia del mes.
 *  - 1 comprobante pendiente → 1 por revisar.
 *  - 2 cuotas activas de octubre + 1 inactiva → 2 cuotas.
 */
class CierreMesAuditoriaTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function escenario(): void
    {
        $sede = $this->sede('Palomar');
        $b1 = $this->bloque($sede, 'Lunes');
        $b2 = $this->bloque($sede, 'Martes');
        $b3 = $this->bloque($sede, 'Jueves');
        $alumno = $this->inscribirAlumno($this->persona('Alumna Cierre'), $b1);
        $otro = $this->inscribirAlumno($this->persona('Alumno Cierre'), $b2);

        Asistencia::query()->create(['alumno_id' => $alumno->id, 'bloque_id' => $b1->id, 'fecha' => '2026-10-05', 'tipo_asistencia' => 'presente']);
        Asistencia::query()->create(['alumno_id' => $otro->id, 'bloque_id' => $b2->id, 'fecha' => '2026-10-06', 'tipo_asistencia' => 'presente']);
        // Septiembre no cuenta para octubre: b3 sigue sin asistencia de octubre.
        Asistencia::query()->create(['alumno_id' => $alumno->id, 'bloque_id' => $b3->id, 'fecha' => '2026-09-30', 'tipo_asistencia' => 'presente']);

        ComprobanteCuotaAlumno::query()->create(['alumno_id' => $alumno->id, 'sede_id' => $sede->id, 'monto_total' => 5000, 'estado' => 'pendiente', 'origen' => 'web', 'fecha_pago' => '2026-10-10']);
        ComprobanteCuotaAlumno::query()->create(['alumno_id' => $alumno->id, 'sede_id' => $sede->id, 'monto_total' => 5000, 'estado' => 'aprobado', 'origen' => 'web', 'fecha_pago' => '2026-10-11']);

        Cuota::query()->create(['nombre' => 'Octubre', 'año' => 2026, 'mes' => 10, 'monto' => 10000, 'alcance' => 'bloque', 'bloque_id' => $b1->id, 'activo' => true]);
        Cuota::query()->create(['nombre' => 'Octubre', 'año' => 2026, 'mes' => 10, 'monto' => 9000, 'alcance' => 'bloque', 'bloque_id' => $b2->id, 'activo' => true]);
        Cuota::query()->create(['nombre' => 'Octubre vieja', 'año' => 2026, 'mes' => 10, 'monto' => 8000, 'alcance' => 'bloque', 'bloque_id' => $b3->id, 'activo' => false]);
    }

    public function test_checklist_cuenta_lo_que_dice_con_cifras_exactas(): void
    {
        $this->escenario();
        $lista = collect(app(CierreMesService::class)->checklist(10, 2026))->keyBy('clave');

        $this->assertFalse($lista['asistencias']['ok']);
        $this->assertSame('1 bloque(s) sin ninguna asistencia cargada.', $lista['asistencias']['detalle']);
        $this->assertFalse($lista['comprobantes']['ok']);
        $this->assertSame('1 pendiente(s).', $lista['comprobantes']['detalle']);
        $this->assertTrue($lista['cuotas']['ok']);
        $this->assertSame('2 cuota(s) activas.', $lista['cuotas']['detalle'], 'La cuota inactiva no cuenta');
        $this->assertNull($lista['facturacion']['ok']);
    }

    public function test_cierre_es_idempotente_y_no_modifica_datos(): void
    {
        $this->escenario();
        $antes = [Asistencia::count(), ComprobanteCuotaAlumno::count(), Cuota::count()];

        $a = app(CierreMesService::class)->checklist(10, 2026);
        $b = app(CierreMesService::class)->checklist(10, 2026);

        $this->assertSame($a, $b);
        $this->assertSame($antes, [Asistencia::count(), ComprobanteCuotaAlumno::count(), Cuota::count()]);
    }

    public function test_pantalla_de_cierre_renderiza_y_solo_la_ve_quien_factura(): void
    {
        $this->escenario();
        $admin = $this->admin('Cierre');
        $this->actingAs($admin)->get(route('operativo.cierre-mes', ['mes' => 10, 'anio' => 2026]))->assertOk()
            ->assertSee('1 pendiente(s).')->assertSee('2 cuota(s) activas.');

        $profe = $this->usuario('Profe Cierre');
        $this->asignarRol($profe->persona, 'profesor', 'bloque', null, $this->bloque($this->sede('Varela'), 'X'));
        $this->actingAs($profe)->get(route('operativo.cierre-mes'))->assertForbidden();
    }
}
