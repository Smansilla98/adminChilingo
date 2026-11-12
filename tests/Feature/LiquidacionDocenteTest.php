<?php

namespace Tests\Feature;

use App\Domain\Docentes\LiquidacionService;
use App\Models\Asistencia;
use App\Models\BloqueHorario;
use App\Models\Gasto;
use App\Models\LiquidacionDocenteMensual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Escenarios;
use Tests\TestCase;

class LiquidacionDocenteTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_horas_desde_asistencias_ajustes_estados_gasto_y_recibo(): void
    {
        $sede = $this->sede('Palomar');
        $bloque = $this->bloque($sede, 'Bloque lunes');
        BloqueHorario::query()->create(['bloque_id' => $bloque->id, 'dia_semana' => 1, 'hora_inicio' => '18:00:00', 'hora_fin' => '20:00:00']);
        $profeUser = $this->usuario('Profe Lunes');
        $profesor = $this->asignarDocente($profeUser->persona, $bloque);
        $alumno = $this->inscribirAlumno($this->persona('Ana'), $bloque);
        // Dos lunes con clase, un feriado (no cuenta) y una asistencia de otro mes.
        foreach (['2026-10-05' => 'presente', '2026-10-12' => 'ausencia_injustificada', '2026-10-19' => 'feriado', '2026-09-28' => 'presente'] as $fecha => $tipo) {
            Asistencia::query()->create(['alumno_id' => $alumno->id, 'bloque_id' => $bloque->id, 'fecha' => $fecha, 'tipo_asistencia' => $tipo]);
        }

        $tesorero = $this->usuario('Tesorera');
        $this->asignarRol($tesorero->persona, 'tesorero');
        $ajena = $this->usuario('Tesorera Varela');
        $this->asignarRol($ajena->persona, 'tesorero', 'sede', $this->sede('Varela'));

        $this->actingAs($ajena->fresh())->post('/liquidaciones/generar', ['anio' => 2026, 'mes' => 10, 'profesor_id' => $profesor->id])->assertForbidden();

        $this->actingAs($tesorero->fresh())
            ->put("/liquidaciones/docentes/{$profesor->id}/valor-hora", ['modalidad' => 'hora', 'valor_hora' => 1000, 'sedes' => [$sede->id => 1500]])->assertRedirect();
        $this->post('/liquidaciones/ajustes', ['profesor_id' => $profesor->id, 'bloque_id' => $bloque->id, 'fecha' => '2026-10-26', 'tipo' => 'suplencia', 'horas' => 2, 'motivo' => 'Cubrió a otro docente'])->assertRedirect();
        $this->post('/liquidaciones/generar', ['anio' => 2026, 'mes' => 10, 'profesor_id' => $profesor->id])->assertRedirect();

        $liq = LiquidacionDocenteMensual::query()->where('profesor_id', $profesor->id)->firstOrFail();
        $this->assertSame(4.0, $liq->horas_dictadas);
        $this->assertSame(2.0, $liq->horas_ajuste);
        $this->assertSame(9000.0, $liq->monto_horas);
        $this->assertSame(9000.0, $liq->monto_total);
        $this->assertSame('borrador', $liq->estado);
        $this->get("/liquidaciones/{$liq->id}")->assertOk()->assertSee('Profe Lunes')->assertSee('Cubrió a otro docente');

        // El docente no ve el borrador.
        Sanctum::actingAs($profeUser->fresh());
        $this->getJson('/api/v1/mi/liquidaciones')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/liquidaciones/{$liq->id}")->assertForbidden();

        $this->actingAs($tesorero->fresh());
        $this->post("/liquidaciones/{$liq->id}/pagar")->assertStatus(422);
        $this->post("/liquidaciones/{$liq->id}/aprobar")->assertRedirect();
        // Aprobada: no se recalcula ni se cambian sus horas.
        $this->post('/liquidaciones/generar', ['anio' => 2026, 'mes' => 10, 'profesor_id' => $profesor->id])->assertSessionHasErrors('estado');
        $this->post('/liquidaciones/ajustes', ['profesor_id' => $profesor->id, 'fecha' => '2026-10-27', 'tipo' => 'ausencia', 'horas' => 2])->assertSessionHasErrors('fecha');
        $this->post("/liquidaciones/{$liq->id}/pagar", ['registrar_gasto' => 1])->assertRedirect();

        $liq->refresh();
        $this->assertSame('pagada', $liq->estado);
        $gasto = Gasto::query()->findOrFail($liq->gasto_ids[0]);
        $this->assertSame('sueldo', $gasto->tipo);
        $this->assertSame($sede->id, (int) $gasto->sede_id);
        $this->assertSame('9000.00', (string) $gasto->monto);

        Sanctum::actingAs($profeUser->fresh());
        $this->getJson('/api/v1/mi/liquidaciones')->assertOk()->assertJsonPath('data.0.monto_total', 9000)->assertJsonPath('data.0.estado', 'pagada');
        $recibo = $this->getJson("/api/v1/liquidaciones/{$liq->id}/recibo")->assertOk();
        $this->assertStringContainsString($liq->numeroRecibo(), $recibo->json('html'));
        $this->actingAs($profeUser->fresh())->get("/liquidaciones/{$liq->id}/recibo")->assertOk()->assertSee('Firma del docente');
        $this->get('/liquidaciones')->assertForbidden();
    }

    public function test_modalidad_porcentaje_por_defecto_usa_el_abono_de_finanzas_y_muestra_ambas(): void
    {
        $sede = $this->sede('Quilmes');
        $bloque = $this->bloque($sede);
        $profesor = $this->asignarDocente($this->persona('Docente'), $bloque);
        $calc = app(LiquidacionService::class)->calcular($profesor, 2026, 10);
        $this->assertSame('porcentaje', $calc['modalidad']);
        $this->assertSame(0.0, $calc['monto_porcentaje']);
        $this->assertSame(0.0, $calc['monto_horas']);
    }
}
