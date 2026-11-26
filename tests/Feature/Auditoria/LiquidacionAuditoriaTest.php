<?php

namespace Tests\Feature\Auditoria;

use App\Domain\Docentes\LiquidacionService;
use App\Models\AjusteHorasDocente;
use App\Models\Asistencia;
use App\Models\BloqueHorario;
use App\Models\Gasto;
use App\Models\LiquidacionDocenteMensual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\Escenarios;
use Tests\TestCase;

/**
 * Auditoría de liquidación docente, octubre 2026, con dos sedes y dos valores hora.
 *
 * Cálculo a mano:
 *  - Palomar (valor 1.500/h): lunes 5, 12 y 19 con clase de 18:00 a 20:00 → 3 × 2 h = 6 h → $9.000.
 *  - Varela (sin valor propio → general 1.000/h): martes 6 y 13 con clase de 16:00 a 18:30 → 2 × 2,5 h = 5 h.
 *    Ausencia de 1 h (resta) → 4 h → $4.000.
 *  - Total: 10 h, $13.000. Gastos por sede: Palomar $9.000 + Varela $4.000 = $13.000.
 */
class LiquidacionAuditoriaTest extends TestCase
{
    use Escenarios, RefreshDatabase;

    public function test_liquidacion_con_dos_sedes_reparto_de_gastos_y_pago_unico(): void
    {
        $palomar = $this->sede('Palomar');
        $varela = $this->sede('Varela');
        $bPal = $this->bloque($palomar, 'Lunes');
        $bVar = $this->bloque($varela, 'Martes');
        BloqueHorario::query()->create(['bloque_id' => $bPal->id, 'dia_semana' => 1, 'hora_inicio' => '18:00:00', 'hora_fin' => '20:00:00']);
        BloqueHorario::query()->create(['bloque_id' => $bVar->id, 'dia_semana' => 2, 'hora_inicio' => '16:00:00', 'hora_fin' => '18:30:00']);

        $persona = $this->persona('Docente Doble');
        $profesor = $this->asignarDocente($persona, $bPal);
        $profesor->bloques()->syncWithoutDetaching([$bVar->id => ['rol' => 'titular']]);
        $alumnoPal = $this->inscribirAlumno($this->persona('Alumna Pal'), $bPal);
        $alumnoVar = $this->inscribirAlumno($this->persona('Alumno Var'), $bVar);

        foreach (['2026-10-05', '2026-10-12', '2026-10-19'] as $f) {
            Asistencia::query()->create(['alumno_id' => $alumnoPal->id, 'bloque_id' => $bPal->id, 'fecha' => $f, 'tipo_asistencia' => 'presente']);
        }
        foreach (['2026-10-06', '2026-10-13'] as $f) {
            Asistencia::query()->create(['alumno_id' => $alumnoVar->id, 'bloque_id' => $bVar->id, 'fecha' => $f, 'tipo_asistencia' => 'presente']);
        }

        $admin = $this->admin('Tesorería');
        $svc = app(LiquidacionService::class);
        $svc->guardarTarifa($admin, $profesor, ['modalidad' => 'hora', 'valor_hora' => 1000, 'sedes' => [$palomar->id => 1500]]);
        AjusteHorasDocente::query()->create([
            'profesor_id' => $profesor->id, 'bloque_id' => $bVar->id, 'fecha' => '2026-10-20', 'tipo' => 'ausencia',
            'horas' => 1, 'motivo' => 'Faltó',
        ]);

        $liq = $svc->generar($admin, $profesor, 2026, 10);

        $this->assertEquals(10.0, $liq->horas_dictadas + $liq->horas_ajuste, 'horas: 6 + 5 - 1 = 10', 0.001);
        $this->assertEquals(13000.0, (float) $liq->monto_total, 'total: 9.000 + 4.000', 0.001);
        $porSede = collect($liq->detalle['por_sede'])->keyBy('sede');
        $this->assertEquals(9000.0, (float) $porSede['Palomar']['monto'], '', 0.001);
        $this->assertEquals(4000.0, (float) $porSede['Varela']['monto'], 'la ausencia resta en Varela', 0.001);
        $this->assertEquals(1000.0, (float) $porSede['Varela']['valor_hora']);

        // Pago: un gasto por sede y la suma de gastos es exactamente el total liquidado.
        $svc->aprobar($admin, $liq);
        $res = $svc->pagar($admin, $liq->fresh(), true);
        $this->assertCount(2, $res['gastos']);
        $gastos = Gasto::query()->whereIn('id', $res['gastos'])->get();
        $this->assertEqualsWithDelta(13000.0, (float) $gastos->sum('monto'), 0.001, 'Los gastos cierran con la liquidación');
        $this->assertEqualsWithDelta(9000.0, (float) $gastos->firstWhere('sede_id', $palomar->id)->monto, 0.001);
        $this->assertSame('pagada', $liq->fresh()->estado);

        // Pagar de nuevo no duplica gastos ni vuelve a pagar.
        try {
            $svc->pagar($admin, $liq->fresh(), true);
            $this->fail('No debía volver a pagar una liquidación pagada');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertStringContainsString('aprobar', $e->getMessage());
        }
        $this->assertSame(2, Gasto::query()->where('subtipo', 'liquidacion_docente')->count());
    }

    public function test_ajuste_que_deja_horas_negativas_no_genera_monto_negativo(): void
    {
        $bloque = $this->bloque($this->sede('Palomar'), 'Lunes');
        BloqueHorario::query()->create(['bloque_id' => $bloque->id, 'dia_semana' => 1, 'hora_inicio' => '18:00:00', 'hora_fin' => '20:00:00']);
        $profesor = $this->asignarDocente($this->persona('Docente Chico'), $bloque);
        $admin = $this->admin('Tesorería');
        app(LiquidacionService::class)->guardarTarifa($admin, $profesor, ['modalidad' => 'hora', 'valor_hora' => 1000, 'sedes' => []]);
        AjusteHorasDocente::query()->create(['profesor_id' => $profesor->id, 'bloque_id' => $bloque->id, 'fecha' => '2026-10-20', 'tipo' => 'ausencia', 'horas' => 5, 'motivo' => 'Ajuste grande']);

        $liq = app(LiquidacionService::class)->generar($admin, $profesor, 2026, 10);

        $this->assertGreaterThanOrEqual(0.0, (float) $liq->monto_total, 'Un mes sin clases no puede liquidar negativo');
    }

    public function test_recalcular_mes_pagado_se_rechaza_con_mensaje_humano(): void
    {
        $bloque = $this->bloque($this->sede('Palomar'), 'Lunes');
        $profesor = $this->asignarDocente($this->persona('Docente Pago'), $bloque);
        $admin = $this->admin('Tesorería');
        $svc = app(LiquidacionService::class);
        $liq = LiquidacionDocenteMensual::query()->create(['profesor_id' => $profesor->id, 'anio' => 2026, 'mes' => 9, 'estado' => 'pagada', 'modalidad' => 'hora', 'monto_total' => 0, 'monto_horas' => 0, 'monto_porcentaje' => 0, 'horas_dictadas' => 0, 'horas_ajuste' => 0, 'detalle' => []]);

        try {
            $svc->generar($admin, $profesor, 2026, 9);
            $this->fail('Un mes pagado no se recalcula');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('ya está', $e->validator->errors()->first('estado'));
        }
        $this->assertSame('pagada', $liq->fresh()->estado);
    }
}
