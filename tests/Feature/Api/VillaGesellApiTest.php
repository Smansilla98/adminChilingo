<?php

namespace Tests\Feature\Api;

use App\Models\VillaGesellInscripto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

class VillaGesellApiTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    private function coordinador()
    {
        $sede = $this->sede('Palomar');
        $u = $this->usuario('Coord');
        $this->asignarRol($u->persona, 'coordinador', 'sede', $sede);

        return [$u->fresh(), $sede];
    }

    public function test_gira_config_inscriptos_con_plaza_y_lista_de_espera(): void
    {
        [$coord, $sede] = $this->coordinador();
        Sanctum::actingAs($coord);
        // aporte_esperado es el valor por día de la gira.
        $this->putJson('/api/v1/villa-gesell/config', ['fecha_inicio' => '2027-01-10', 'fecha_fin' => '2027-01-14', 'cupo_maximo' => 2, 'aporte_esperado' => 10000])
            ->assertOk()->assertJsonPath('config.dias', 5);

        $a = $this->inscribirAlumno($this->persona('Ana'), $this->bloque($sede));
        $b = $this->inscribirAlumno($this->persona('Beto'), $this->bloque($sede, 'B'));
        $sug = $this->getJson('/api/v1/villa-gesell/inscriptos/nueva')->assertOk();
        $this->assertSame(1, $sug->json('data.plaza'));

        $base = ['estado_pago' => 'sena', 'monto_esperado' => 0, 'monto_pagado' => 10000, 'fecha_desde' => '2027-01-10', 'fecha_hasta' => '2027-01-14', 'talle_remera' => 'M', 'tambor_principal' => 'Repique'];
        $id = $this->postJson('/api/v1/villa-gesell/inscriptos', ['alumno_id' => $a->id, 'plaza' => 1, 'calcular_aporte' => true] + $base)->assertCreated()
            ->assertJsonPath('data.monto_esperado', 50000)->assertJsonPath('data.saldo', 40000)->json('data.id');
        $this->postJson('/api/v1/villa-gesell/inscriptos', ['alumno_id' => $b->id, 'plaza' => 1] + $base)->assertStatus(422)->assertJsonValidationErrors('plaza');
        $this->postJson('/api/v1/villa-gesell/inscriptos', ['alumno_id' => $a->id, 'plaza' => 2] + $base)->assertStatus(422)->assertJsonValidationErrors('alumno_id');
        $this->postJson('/api/v1/villa-gesell/inscriptos', ['alumno_id' => $b->id, 'plaza' => 2, 'lista_espera' => true] + $base)->assertCreated()
            ->assertJsonPath('data.lista_espera', true)->assertJsonPath('data.plaza', null);

        $this->putJson('/api/v1/villa-gesell/config', ['fecha_inicio' => '2027-01-10', 'fecha_fin' => '2027-01-14', 'cupo_maximo' => 0, 'aporte_esperado' => 10000])->assertStatus(422);
        $this->assertSame([], $this->getJson('/api/v1/villa-gesell/alumnos-disponibles')->assertOk()->json('data'), 'los dos ya están inscriptos');
        $this->assertCount(1, $this->getJson('/api/v1/villa-gesell/inscriptos?lista=espera')->assertOk()->json('data'));

        $this->putJson("/api/v1/villa-gesell/inscriptos/{$id}", ['alumno_id' => $a->id, 'plaza' => 1, 'estado_pago' => 'pago', 'monto_pagado' => 50000] + $base)->assertOk()->assertJsonPath('data.estado_pago', 'pago');
        $plan = $this->getJson('/api/v1/villa-gesell')->assertOk();
        $this->assertSame(1, $plan->json('plan.plazas_ocupadas'));
        $this->assertSame(1, $plan->json('plan.lista_espera'));
        $this->deleteJson("/api/v1/villa-gesell/inscriptos/{$id}")->assertOk();
        $this->assertSame(1, VillaGesellInscripto::query()->count());
    }

    public function test_calendario_gastos_insumos_y_altas_rapidas(): void
    {
        [$coord] = $this->coordinador();
        Sanctum::actingAs($coord);
        $this->putJson('/api/v1/villa-gesell/config', ['fecha_inicio' => '2027-01-10', 'fecha_fin' => '2027-01-12', 'cupo_maximo' => 30, 'aporte_esperado' => 30000])->assertOk();

        $dias = $this->getJson('/api/v1/villa-gesell/calendario')->assertOk()->json('data');
        $this->assertCount(3, $dias);
        $dia = $dias[0]['id'];
        $this->postJson("/api/v1/villa-gesell/dias/{$dia}/slots", ['cantidad' => 2])->assertCreated();
        $t = $this->postJson("/api/v1/villa-gesell/dias/{$dia}/tocadas", ['que' => 'Corso', 'hora' => '21:00', 'donde' => 'Av. 3'])->assertCreated()->assertJsonPath('data.orden', 3)->json('data.id');
        $this->putJson("/api/v1/villa-gesell/tocadas/{$t}", ['que' => 'Corso central', 'hora' => '21:30'])->assertOk()->assertJsonPath('data.hora', '21:30');
        $this->postJson("/api/v1/villa-gesell/dias/{$dia}/tocadas", ['hora' => '99'])->assertStatus(422)->assertJsonValidationErrors(['que', 'hora']);
        $this->deleteJson("/api/v1/villa-gesell/tocadas/{$t}")->assertOk();

        $this->postJson('/api/v1/villa-gesell/gastos', ['tipo' => 'diario', 'concepto' => 'Comida', 'monto' => 1000, 'modo' => 'total'])->assertCreated()
            ->assertJsonPath('data.modo', 'por_dia')->assertJsonPath('data.proyectado', 3000);
        $this->postJson('/api/v1/villa-gesell/insumos', ['nombre' => 'Parches', 'categoria' => 'percusion', 'cantidad' => 4, 'costo_unitario' => 500])->assertCreated()->assertJsonPath('data.costo_total', 2000);
        $this->assertEquals(2000, $this->getJson('/api/v1/villa-gesell/insumos')->json('total'));
        $this->assertEquals(3000, $this->getJson('/api/v1/villa-gesell/gastos')->json('plan.gastos_totales'));

        $prof = $this->postJson('/api/v1/villa-gesell/profesores-rapidos', ['nombre' => 'Nuevo profe'])->assertCreated()->json('data.id');
        $bloque = $this->postJson('/api/v1/villa-gesell/bloques-rapidos', ['nombre' => 'Gira', 'profesor_id' => $prof])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/villa-gesell/alumnos-rapidos', ['nombre_apellido' => 'Invitada', 'dni' => '40111222', 'bloque_id' => $bloque])->assertCreated()->assertJsonPath('data.profesor', 'Nuevo profe');
        $this->postJson('/api/v1/villa-gesell/alumnos-rapidos', ['nombre_apellido' => 'Otra', 'dni' => '40.111.222'])->assertStatus(422)->assertJsonValidationErrors('dni');
    }

    public function test_sin_permiso_de_gira_es_403(): void
    {
        $contador = $this->usuario('Contador');
        $this->asignarRol($contador->persona, 'contador');
        Sanctum::actingAs($contador->fresh());
        $this->getJson('/api/v1/villa-gesell')->assertForbidden();
        $this->postJson('/api/v1/villa-gesell/inscriptos', [])->assertForbidden();
    }
}
