<?php

namespace App\Domain\Operativo;

use App\Models\Asistencia;
use App\Models\Bloque;
use App\Models\ComprobanteCuotaAlumno;
use App\Models\Cuota;
use App\Models\FacturacionMensual;
use Illuminate\Support\Facades\Schema;

/**
 * Checklist del cierre de mes (toda la escuela): asistencias cargadas, comprobantes
 * por revisar, cuotas emitidas y facturación. Web y API muestran lo mismo.
 */
class CierreMesService
{
    /**
     * @return list<array{clave: string, titulo: string, ok: ?bool, detalle: string}>
     */
    public function checklist(int $mes, int $anio): array
    {
        $bloquesSinAsist = 0;
        if (Schema::hasTable('bloques')) {
            foreach (Bloque::query()->where('activo', true)->pluck('id') as $bloqueId) {
                $hay = Schema::hasTable('asistencias') && Asistencia::query()->where('bloque_id', $bloqueId)->whereMonth('fecha', $mes)->whereYear('fecha', $anio)->exists();
                if (! $hay) {
                    $bloquesSinAsist++;
                }
            }
        }
        $compPend = Schema::hasTable('comprobantes_cuota_alumnos') ? ComprobanteCuotaAlumno::query()->where('estado', 'pendiente')->count() : 0;
        $cuotasMes = Schema::hasTable('cuotas') ? Cuota::query()->where('activo', true)->where('mes', $mes)->where('año', $anio)->count() : 0;
        $facturas = Schema::hasTable('facturacion_mensual') ? FacturacionMensual::query()->where('mes', $mes)->where('año', $anio)->count() : 0;

        return [
            [
                'clave' => 'asistencias',
                'titulo' => 'Asistencias del mes',
                'ok' => $bloquesSinAsist === 0,
                'detalle' => $bloquesSinAsist === 0 ? 'Todos los bloques activos tienen al menos un registro.' : "{$bloquesSinAsist} bloque(s) sin ninguna asistencia cargada.",
            ],
            [
                'clave' => 'comprobantes',
                'titulo' => 'Comprobantes por revisar',
                'ok' => $compPend === 0,
                'detalle' => $compPend === 0 ? 'No hay comprobantes pendientes.' : "{$compPend} pendiente(s).",
            ],
            [
                'clave' => 'cuotas',
                'titulo' => 'Cuotas emitidas del mes',
                'ok' => $cuotasMes > 0,
                'detalle' => $cuotasMes > 0 ? "{$cuotasMes} cuota(s) activas." : 'No hay cuotas cargadas para este mes.',
            ],
            [
                'clave' => 'facturacion',
                'titulo' => 'Facturación mensual',
                'ok' => null,
                'detalle' => $facturas > 0 ? "{$facturas} carga(s) de facturación del mes. Revisá que sean coherentes con los cobros." : 'Revisá que el mes esté generado y coherente con los cobros.',
            ],
        ];
    }

    /**
     * Desglose de la cobranza del mes, por mes de cuota (no por fecha de cobro).
     *
     * Un cobro se registra cuando se paga la cuota, aunque sea de un mes anterior: por eso
     * el mes se separa en lo cobrado de cuotas del propio mes, lo cobrado de cuotas de meses
     * anteriores (arrastre) y lo cobrado de cuotas de meses posteriores (adelantado).
     * El recargo por mora no se guarda aparte: se aproxima como lo pagado por encima del
     * monto de la cuota en los cobros de arrastre.
     * El saldo arrastrado es lo que todavía deben los alumnos de meses anteriores al cierre (a la fecha de hoy).
     *
     * @return array{cobrado_mes: float, cobrado_arrastre: float, cobrado_adelantado: float, recargo_aproximado: float, cobrado_total: float, saldo_arrastrado: float, alumnos_con_saldo: int}
     */
    public function cobranza(int $mes, int $anio): array
    {
        $inicio = \Illuminate\Support\Carbon::create($anio, $mes, 1)->startOfDay();
        $fin = $inicio->copy()->endOfMonth();
        $periodo = $anio * 100 + $mes;

        $lineas = \App\Models\PagoDetalle::query()
            ->join('pagos', 'pagos.id', '=', 'pago_detalles.pago_id')
            ->join('cuotas', 'cuotas.id', '=', 'pago_detalles.cuota_id')
            ->whereNull('pagos.anulado_at')
            ->whereBetween('pagos.fecha_pago', [$inicio->toDateString(), $fin->toDateString()])
            ->get(['pago_detalles.monto', 'cuotas.año as anio', 'cuotas.mes as mes', 'cuotas.monto as cuota_monto']);

        $cobradoMes = $cobradoArrastre = $cobradoAdelantado = $recargo = 0.0;
        foreach ($lineas as $l) {
            $monto = (float) $l->monto;
            $periodoCuota = (int) $l->anio * 100 + (int) $l->mes;
            if ($periodoCuota === $periodo) {
                $cobradoMes += $monto;
            } elseif ($periodoCuota < $periodo) {
                $cobradoArrastre += $monto;
                $recargo += max(0.0, $monto - (float) $l->cuota_monto);
            } else {
                $cobradoAdelantado += $monto;
            }
        }

        // Saldo impago de cuotas anteriores al mes cerrado, por alumno (mismo cálculo que el estado de cuenta).
        $saldoArrastrado = 0.0;
        $alumnosConSaldo = 0;
        $estado = app(\App\Domain\Finanzas\EstadoCuentaService::class);
        foreach (\App\Models\Alumno::query()->where('activo', true)->get() as $alumno) {
            $saldoAlumno = 0.0;
            foreach ([$anio - 1, $anio] as $a) {
                foreach ($estado->paraAlumno($alumno, $a)['items'] as $item) {
                    if (($item['anio'] * 100 + (int) ($item['mes'] ?? 0)) < $periodo && $item['saldo'] > 0) {
                        $saldoAlumno += (float) $item['saldo'];
                    }
                }
            }
            if ($saldoAlumno > 0) {
                $saldoArrastrado += $saldoAlumno;
                $alumnosConSaldo++;
            }
        }

        return [
            'cobrado_mes' => round($cobradoMes, 2),
            'cobrado_arrastre' => round($cobradoArrastre, 2),
            'cobrado_adelantado' => round($cobradoAdelantado, 2),
            'recargo_aproximado' => round($recargo, 2),
            'cobrado_total' => round($cobradoMes + $cobradoArrastre + $cobradoAdelantado, 2),
            'saldo_arrastrado' => round($saldoArrastrado, 2),
            'alumnos_con_saldo' => $alumnosConSaldo,
        ];
    }
}
