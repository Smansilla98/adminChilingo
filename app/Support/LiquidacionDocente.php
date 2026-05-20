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
     * @param  array<int, array<string, mixed>>  $lineas
     * @param  \Illuminate\Support\Collection<int, Cuota>  $cuotasPorId
     * @return array<int, float>
     */
    public static function abonosPorLinea(array $lineas, $cuotasPorId): array
    {
        $out = [];
        foreach ($lineas as $idx => $linea) {
            $cuota = $cuotasPorId->get((int) ($linea['cuota_id'] ?? 0));
            $out[$idx] = $cuota ? self::montoAbonoDocente($cuota) : 0.0;
        }

        return $out;
    }
}
