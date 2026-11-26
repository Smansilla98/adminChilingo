<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

/**
 * Regresión del contrato JSON de la gira (inscriptos, gastos con papelera e insumos).
 */
class VillaGesellPresentacionTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_inscripto_expone_su_ficha_y_el_filtro_de_lista(): void
    {
        $sede = $this->sede('Palomar');
        $coord = $this->usuario('Coord');
        $this->asignarRol($coord->persona, 'coordinador', 'sede', $sede);
        Sanctum::actingAs($coord->fresh());
        $this->putJson('/api/v1/villa-gesell/config', ['fecha_inicio' => '2027-01-10', 'fecha_fin' => '2027-01-14', 'cupo_maximo' => 1, 'aporte_esperado' => 10000])->assertOk();

        $ana = $this->inscribirAlumno($this->persona('Ana'), $this->bloque($sede));
        $beto = $this->inscribirAlumno($this->persona('Beto'), $this->bloque($sede, 'B'));
        $id = $this->postJson('/api/v1/villa-gesell/inscriptos', ['alumno_id' => $ana->id, 'plaza' => 1, 'estado_pago' => 'pendiente', 'monto_esperado' => 10000, 'monto_pagado' => 0, 'fecha_desde' => '2027-01-10', 'fecha_hasta' => '2027-01-14'])
            ->assertCreated()->json('data.id');
        $this->postJson('/api/v1/villa-gesell/inscriptos', ['alumno_id' => $beto->id, 'lista_espera' => true, 'estado_pago' => 'pendiente', 'monto_esperado' => 10000, 'monto_pagado' => 0, 'fecha_desde' => '2027-01-10', 'fecha_hasta' => '2027-01-14'])
            ->assertCreated();

        $this->getJson("/api/v1/villa-gesell/inscriptos/{$id}")->assertOk()
            ->assertJsonPath('data.alumno.nombre', 'Ana')
            ->assertJsonPath('data.lista_espera', false)
            ->assertJsonPath('data.plaza', 1)
            ->assertJsonStructure(['data' => ['id', 'alumno' => ['id', 'nombre', 'sede', 'persona_id'], 'estado_pago', 'estado_pago_nombre', 'monto_esperado', 'monto_pagado', 'saldo', 'plaza', 'lista_espera', 'fecha_desde', 'fecha_hasta', 'dias', 'tambor_principal_origen', 'notas']]);

        $espera = $this->getJson('/api/v1/villa-gesell/inscriptos?lista=espera')->assertOk()->json('data');
        $this->assertCount(1, $espera);
        $this->assertTrue($espera[0]['lista_espera']);
    }

    public function test_gastos_papelera_restaurar_e_insumos_editables(): void
    {
        $sede = $this->sede('Palomar');
        $coord = $this->usuario('Coord');
        $this->asignarRol($coord->persona, 'coordinador', 'sede', $sede);
        Sanctum::actingAs($coord->fresh());
        $this->putJson('/api/v1/villa-gesell/config', ['fecha_inicio' => '2027-01-10', 'fecha_fin' => '2027-01-12', 'cupo_maximo' => 30, 'aporte_esperado' => 30000])->assertOk();

        $gasto = $this->postJson('/api/v1/villa-gesell/gastos', ['tipo' => 'diario', 'concepto' => 'Comida', 'monto' => 1000, 'modo' => 'total'])
            ->assertCreated()->assertJsonStructure(['data' => ['id', 'tipo_nombre', 'proyectado']])->json('data.id');

        $this->putJson("/api/v1/villa-gesell/gastos/{$gasto}", ['tipo' => 'diario', 'concepto' => 'Comida y bebida', 'monto' => 2000, 'modo' => 'total'])
            ->assertOk()->assertJsonPath('data.concepto', 'Comida y bebida')->assertJsonPath('data.monto', 2000);

        $this->deleteJson("/api/v1/villa-gesell/gastos/{$gasto}", ['motivo' => 'Duplicado'])->assertOk()->assertJsonPath('ok', true);
        $this->getJson('/api/v1/villa-gesell/gastos')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/villa-gesell/gastos/papelera')->assertOk()
            ->assertJsonPath('data.0.id', $gasto)
            ->assertJsonPath('data.0.motivo_eliminacion', 'Duplicado')
            ->assertJsonPath('data.0.concepto', 'Comida y bebida');

        $this->postJson("/api/v1/villa-gesell/gastos/{$gasto}/restaurar")->assertOk()->assertJsonPath('data.id', $gasto);
        $this->getJson('/api/v1/villa-gesell/gastos')->assertOk()->assertJsonCount(1, 'data');

        $insumo = $this->postJson('/api/v1/villa-gesell/insumos', ['nombre' => 'Parches', 'categoria' => 'percusion', 'cantidad' => 4, 'costo_unitario' => 500])
            ->assertCreated()->json('data.id');
        $this->putJson("/api/v1/villa-gesell/insumos/{$insumo}", ['nombre' => 'Parches grandes', 'categoria' => 'percusion', 'cantidad' => 5, 'costo_unitario' => 500])
            ->assertOk()->assertJsonPath('data.nombre', 'Parches grandes')->assertJsonPath('data.costo_total', 2500);
        $this->deleteJson("/api/v1/villa-gesell/insumos/{$insumo}")->assertOk();
        $this->getJson('/api/v1/villa-gesell/insumos')->assertOk()->assertJsonPath('total', 0);
    }
}
