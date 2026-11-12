<?php

namespace Tests\Feature;

use App\Domain\Agenda\ShowPlanService;
use App\Domain\Inventario\AvisosPrestamos;
use App\Models\InventarioItem;
use App\Models\InventarioMovimiento;
use App\Models\InventarioPrestamo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

/** Préstamos de instrumentos a alumnos: disponibilidad, alcance, devolución, vista del alumno y avisos. */
class PrestamosInventarioTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function instrumento(\App\Models\Sede $sede, string $nombre = 'Surdo 22'): InventarioItem
    {
        $item = InventarioItem::query()->create([
            'sede_id' => $sede->id, 'tipo' => 'instrumento', 'nombre' => $nombre, 'cantidad' => 1,
            'propietario_tipo' => 'escuela', 'estado' => 'bueno', 'es_consumible' => false,
        ]);
        $item->asegurarCodigo();

        return $item->fresh();
    }

    public function test_prestar_marca_el_instrumento_no_disponible_y_devolver_lo_libera(): void
    {
        $palomar = $this->sede('Palomar');
        $bloque = $this->bloque($palomar);
        $ana = $this->usuario('Ana');
        $alumna = $this->inscribirAlumno($ana->persona, $bloque);
        $item = $this->instrumento($palomar);
        Sanctum::actingAs($this->admin());

        $id = $this->postJson("/api/v1/inventario/{$item->id}/prestamos", [
            'alumno_id' => $alumna->id, 'entregado_el' => now()->toDateString(),
            'devolucion_prevista' => now()->addWeek()->toDateString(), 'deposito' => 5000, 'condicion_entrega' => 'bueno',
        ])->assertCreated()->assertJsonPath('data.estado', 'prestado')->assertJsonPath('data.responsable', 'Ana')->json('data.id');

        $this->getJson("/api/v1/inventario/{$item->id}")->assertOk()->assertJsonPath('data.prestado', true)->assertJsonPath('data.prestamo', 'Ana');
        $this->postJson("/api/v1/inventario/{$item->id}/prestamos", [
            'alumno_id' => $alumna->id, 'entregado_el' => now()->toDateString(), 'devolucion_prevista' => now()->addDay()->toDateString(), 'condicion_entrega' => 'bueno',
        ])->assertStatus(422);
        $this->assertFalse(collect(app(ShowPlanService::class)->catalogo()['instrumentos'])->contains('id', $item->id), 'un instrumento prestado no se puede sumar a un show');

        $this->postJson("/api/v1/inventario/prestamos/{$id}/devolucion", [
            'devuelto_el' => now()->toDateString(), 'condicion_devolucion' => 'reparacion', 'deposito_devuelto' => true,
        ])->assertOk()->assertJsonPath('data.estado', 'devuelto')->assertJsonPath('data.condicion_devolucion', 'reparacion');

        $this->assertSame('reparacion', $item->fresh()->estado, 'la condición con que vuelve queda como estado del ítem');
        $this->getJson("/api/v1/inventario/{$item->id}")->assertJsonPath('data.prestado', false);
        $this->assertSame(['prestamo', 'devolucion'], InventarioMovimiento::query()->where('inventario_item_id', $item->id)->orderBy('id')->pluck('tipo')->intersect(['prestamo', 'devolucion'])->values()->all());
        $this->postJson("/api/v1/inventario/prestamos/{$id}/devolucion", ['devuelto_el' => now()->toDateString(), 'condicion_devolucion' => 'bueno'])->assertStatus(422);
    }

    public function test_encargado_solo_presta_instrumentos_de_su_sede(): void
    {
        $palomar = $this->sede('Palomar');
        $varela = $this->sede('Varela');
        $ajeno = $this->instrumento($varela, 'Repique ajeno');
        $encargado = $this->usuario('Encargado');
        $this->asignarRol($encargado->persona, 'encargado', 'sede', $palomar);
        Sanctum::actingAs($encargado->fresh());
        $datos = ['responsable_nombre' => 'Un vecino', 'entregado_el' => now()->toDateString(), 'devolucion_prevista' => now()->addDay()->toDateString(), 'condicion_entrega' => 'bueno'];

        $this->postJson("/api/v1/inventario/{$ajeno->id}/prestamos", $datos)->assertForbidden();
        $propio = $this->instrumento($palomar, 'Propio');
        $this->postJson("/api/v1/inventario/{$propio->id}/prestamos", [...$datos, 'responsable_nombre' => null])->assertStatus(422);
        $this->getJson('/api/v1/inventario/prestamos')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_el_alumno_ve_sus_prestamos_y_no_los_de_otros(): void
    {
        $bloque = $this->bloque($this->sede('Palomar'));
        $ana = $this->usuario('Ana');
        $alumna = $this->inscribirAlumno($ana->persona, $bloque);
        $beto = $this->usuario('Beto');
        $betoAlumno = $this->inscribirAlumno($beto->persona, $bloque);
        $item = $this->instrumento($this->sede('Palomar'));
        InventarioPrestamo::query()->create(['inventario_item_id' => $item->id, 'alumno_id' => $alumna->id, 'entregado_el' => now()->subDays(3), 'devolucion_prevista' => now()->addDays(4), 'condicion_entrega' => 'bueno']);
        InventarioPrestamo::query()->create(['inventario_item_id' => $this->instrumento($this->sede('Palomar'), 'Otro')->id, 'alumno_id' => $betoAlumno->id, 'entregado_el' => now(), 'devolucion_prevista' => now()->addDays(4), 'condicion_entrega' => 'bueno']);

        Sanctum::actingAs($ana->fresh());
        $this->getJson('/api/v1/mi/prestamos')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.item.nombre', 'Surdo 22')->assertJsonPath('data.0.estado', 'prestado');
    }

    public function test_avisa_el_vencido_una_sola_vez_al_alumno_y_a_la_administracion(): void
    {
        $bloque = $this->bloque($this->sede('Palomar'));
        $ana = $this->usuario('Ana');
        $alumna = $this->inscribirAlumno($ana->persona, $bloque);
        $admin = $this->admin();
        $item = $this->instrumento($this->sede('Palomar'));
        InventarioPrestamo::query()->create(['inventario_item_id' => $item->id, 'alumno_id' => $alumna->id, 'entregado_el' => now()->subWeeks(2), 'devolucion_prevista' => now()->subDays(2), 'condicion_entrega' => 'bueno']);
        InventarioPrestamo::query()->create(['inventario_item_id' => $this->instrumento($this->sede('Palomar'), 'Vigente')->id, 'alumno_id' => $alumna->id, 'entregado_el' => now(), 'devolucion_prevista' => now()->addDays(5), 'condicion_entrega' => 'bueno']);

        $this->assertSame(2, app(AvisosPrestamos::class)->vencidos());
        $this->assertSame(0, app(AvisosPrestamos::class)->vencidos(), 'no se repite');
        $aviso = $ana->fresh()->notifications()->first();
        $this->assertSame('Devolución pendiente: Surdo 22', $aviso->data['titulo']);
        $this->assertSame(1, $admin->fresh()->notifications()->count());
        $this->artisan('chilinga:avisos prestamos-vencidos')->assertSuccessful();
    }

    public function test_pantallas_web_prestar_y_devolver(): void
    {
        $palomar = $this->sede('Palomar');
        $bloque = $this->bloque($palomar);
        $ana = $this->usuario('Ana');
        $alumna = $this->inscribirAlumno($ana->persona, $bloque);
        $item = $this->instrumento($palomar);
        $admin = $this->admin();

        $this->actingAs($admin)->post("/inventarios/{$item->id}/prestamos", [
            'alumno_id' => $alumna->id, 'entregado_el' => now()->toDateString(), 'devolucion_prevista' => now()->addWeek()->toDateString(),
            'condicion_entrega' => 'bueno', 'deposito' => '3000',
        ])->assertRedirect();
        $this->actingAs($admin)->get("/inventarios/{$item->id}")->assertOk()->assertSee('Préstamo')->assertSee('Ana');

        $prestamo = InventarioPrestamo::query()->firstOrFail();
        $this->actingAs($admin)->post("/inventario-prestamos/{$prestamo->id}/devolucion", [
            'devuelto_el' => now()->toDateString(), 'condicion_devolucion' => 'bueno', 'deposito_devuelto' => '1',
        ])->assertRedirect();
        $this->assertNotNull($prestamo->fresh()->devuelto_el);
        $this->assertTrue((bool) $prestamo->fresh()->deposito_devuelto);
    }
}
