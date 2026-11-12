<?php

namespace App\Support;

use App\Models\Cuota;
use App\Models\Sede;

class LiquidacionDocente
{
    /**
     * Sede de referencia para liquidar una cuota (bloque → sede de cuota → sede explícita).
     */
    public static function sedeParaCuota(Cuota $cuota): ?Sede
    {
        $sede = $cuota->bloque?->sede ?? $cuota->sede;
        if ($sede) {
            return $sede;
        }

        return null;
    }

    /**
     * Abono docente preestablecido según reglas de la sede (ej. Banfield: $9.600 de cuota $24.000).
     */
    public static function montoAbonoDocente(Cuota $cuota): float
    {
        $sede = self::sedeParaCuota($cuota);
        if (! $sede || ! \Illuminate\Support\Facades\Schema::hasColumn('sedes', 'liquidacion_porc_docente')) {
            return 0.0;
        }

        return $sede->montoAbonoDocenteDesdeCuota((float) $cuota->monto);
    }

    /**
     * Abono docente de cada línea. Si la línea paga solo una parte de la cuota (pago parcial),
     * el abono se prorratea según lo que se cobra sobre el neto de la cuota (`$netoPorCuota`,
     * clave = id de cuota). Sin neto, o si se paga la cuota entera, es el abono completo.
     *
     * @param  array<int, array<string, mixed>>  $lineas
     * @param  \Illuminate\Support\Collection<int, Cuota>  $cuotasPorId
     * @param  array<int, float>  $netoPorCuota
     * @return array<int, float>
     */
    public static function abonosPorLinea(array $lineas, $cuotasPorId, array $netoPorCuota = []): array
    {
        $out = [];
        foreach ($lineas as $idx => $linea) {
            $cuota = $cuotasPorId->get((int) ($linea['cuota_id'] ?? 0));
            if (! $cuota) {
                $out[$idx] = 0.0;

                continue;
            }
            $abono = self::montoAbonoDocente($cuota);
            $neto = (float) ($netoPorCuota[(int) $cuota->id] ?? 0);
            $factor = $neto > 0 ? min(1.0, round((float) ($linea['monto'] ?? 0) / $neto, 6)) : 1.0;
            $out[$idx] = round($abono * $factor, 2);
        }

        return $out;
    }
}
