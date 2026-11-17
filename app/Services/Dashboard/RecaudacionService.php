<?php

namespace App\Services\Dashboard;

use App\Models\Gasto;
use App\Models\Pago;
use App\Services\AmbitoSedeService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Recaudación de las últimas semanas, cobrado del mes y series de ingresos y gastos de seis meses.
 */
class RecaudacionService
{
    /**
     * @return array<string, mixed>
     */
    public function armar(?array $filtroSedes, AmbitoSedeService $ambito, int $anioActual, int $mesActual): array
    {
        // Recaudación últimas 6 semanas (sum monto_total por fecha_pago)
        $recaudacion = collect(range(5, 0))->map(function ($i) use ($filtroSedes, $ambito) {
            $inicio = Carbon::now()->startOfWeek(Carbon::MONDAY)->subWeeks($i);
            $fin = $inicio->copy()->endOfWeek(Carbon::SUNDAY);
            $q = Pago::query()->vigentes()->whereBetween('fecha_pago', [$inicio->toDateString(), $fin->toDateString()]);
            if ($filtroSedes !== null) {
                $ambito->aplicarPagos($q, $filtroSedes);
            }

            return (float) $q->sum('monto_total');
        });

        $cobradoMesQ = Pago::query()->vigentes()
            ->whereYear('fecha_pago', $anioActual)
            ->whereMonth('fecha_pago', $mesActual);
        if ($filtroSedes !== null) {
            $ambito->aplicarPagos($cobradoMesQ, $filtroSedes);
        }
        $cobradoMes = (float) $cobradoMesQ->sum('monto_total');

        $chartLabels = [];
        $chartIngresos = [];
        $chartGastos = [];
        for ($i = 5; $i >= 0; $i--) {
            $d = now()->subMonths($i);
            $chartLabels[] = $d->locale('es')->translatedFormat('M y');
            $ingQ = Pago::query()->vigentes()
                ->whereYear('fecha_pago', $d->year)
                ->whereMonth('fecha_pago', $d->month);
            if ($filtroSedes !== null) {
                $ambito->aplicarPagos($ingQ, $filtroSedes);
            }
            $chartIngresos[] = (float) $ingQ->sum('monto_total');
            if (Schema::hasTable('gastos')) {
                $gasQ = Gasto::query()->whereYear('fecha', $d->year)->whereMonth('fecha', $d->month);
                if ($filtroSedes !== null) {
                    $ambito->aplicarGastos($gasQ, $filtroSedes);
                }
                $chartGastos[] = (float) $gasQ->sum('monto');
            } else {
                $chartGastos[] = 0.0;
            }
        }

        return compact(
            'recaudacion',
            'cobradoMes',
            'chartLabels',
            'chartIngresos',
            'chartGastos'
        );
    }
}
