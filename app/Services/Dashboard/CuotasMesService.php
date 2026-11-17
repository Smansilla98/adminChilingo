<?php

namespace App\Services\Dashboard;

use App\Models\Cuota;
use App\Services\AmbitoSedeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cuotas emitidas del mes, cuántas están abonadas y el porcentaje de cada una.
 */
class CuotasMesService
{
    public function __construct(private readonly CobrosPendientesService $cobros) {}

    /**
     * @return array<string, mixed>
     */
    public function armar(?array $filtroSedes, AmbitoSedeService $ambito, int $anioActual, int $mesActual): array
    {
        // Total "cuotas emitidas" del mes = suma de asignaciones (cuota_alumno o todos los alumnos del bloque)
        $cuotasMesQ = Cuota::query()
            ->with([
                'alumnos' => fn ($q) => $q->where('activo', true)->select('alumnos.id'),
                'bloque.sede',
                'sede',
            ])
            ->where('año', $anioActual)
            ->where('mes', $mesActual)
            ->where('activo', true);
        if ($filtroSedes !== null) {
            $ambito->aplicarCuotas($cuotasMesQ, $filtroSedes);
        }
        $cuotasMes = $cuotasMesQ->get();
        if ($filtroSedes !== null) {
            $cuotasMes = $cuotasMes->filter(fn (Cuota $c) => $ambito->cuotaTocaSedes($c, $filtroSedes))->values();
        }

        $cuotasTotal = 0;
        foreach ($cuotasMes as $c) {
            $cuotasTotal += $this->cobros->contarObjetivoCuota($c, $filtroSedes, $ambito);
        }

        $cuotasAbonadasQ = DB::table('pago_detalles')
            ->join('pagos', 'pago_detalles.pago_id', '=', 'pagos.id')
            ->join('cuotas', 'pago_detalles.cuota_id', '=', 'cuotas.id')
            ->whereYear('pagos.fecha_pago', $anioActual)
            ->whereMonth('pagos.fecha_pago', $mesActual)
            ->where('cuotas.año', $anioActual)
            ->where('cuotas.mes', $mesActual);
        if ($filtroSedes !== null) {
            $cuotasAbonadasQ->join('alumnos', 'pago_detalles.alumno_id', '=', 'alumnos.id')
                ->where(function ($q) use ($filtroSedes) {
                    $ids = $filtroSedes !== [] ? $filtroSedes : [0];
                    $q->whereIn('alumnos.sede_id', $ids);
                    if (Schema::hasTable('alumno_bloque') && Schema::hasTable('bloques')) {
                        $q->orWhereExists(function ($sub) use ($ids) {
                            $sub->select(DB::raw(1))
                                ->from('alumno_bloque')
                                ->join('bloques', 'bloques.id', '=', 'alumno_bloque.bloque_id')
                                ->whereColumn('alumno_bloque.alumno_id', 'alumnos.id')
                                ->whereIn('bloques.sede_id', $ids);
                        });
                    }
                });
        }
        $cuotasAbonadas = (int) $cuotasAbonadasQ
            ->distinct()
            ->count(DB::raw("concat(pago_detalles.alumno_id,'-',pago_detalles.cuota_id)"));

        $cuotasPendientes = max(0, $cuotasTotal - $cuotasAbonadas);
        $pctAbonadas = $cuotasTotal > 0 ? round(($cuotasAbonadas / $cuotasTotal) * 100) : 0;
        $pctPendientes = $cuotasTotal > 0 ? round(($cuotasPendientes / $cuotasTotal) * 100) : 0;

        return compact(
            'cuotasMes',
            'cuotasTotal',
            'cuotasAbonadas',
            'cuotasPendientes',
            'pctAbonadas',
            'pctPendientes'
        );
    }
}
