<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class VillaGesellCalculo
{
    public static function cantidadDias(?Carbon $inicio, ?Carbon $fin): int
    {
        if (! $inicio || ! $fin) {
            return 0;
        }

        return (int) $inicio->diffInDays($fin) + 1;
    }

    /**
     * @return list<Carbon>
     */
    public static function rangoFechas(?Carbon $inicio, ?Carbon $fin): array
    {
        $out = [];
        if (! $inicio || ! $fin || $fin->lt($inicio)) {
            return $out;
        }
        for ($d = $inicio->copy(); $d->lte($fin); $d->addDay()) {
            $out[] = $d->copy();
        }

        return $out;
    }

    public static function proyectarGasto(float $monto, string $tipo, string $modo, int $diasGira): float
    {
        if ($modo === 'por_dia' || $tipo === 'diario') {
            return $monto * max(0, $diasGira);
        }

        return $monto;
    }
}
