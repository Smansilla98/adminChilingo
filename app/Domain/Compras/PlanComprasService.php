<?php

namespace App\Domain\Compras;

use App\Models\Sede;
use App\Models\User;

/**
 * Plan de compras: por sede, alumnos, carga horaria e instrumentos de la escuela, y una
 * sugerencia de tambores y parches (referencia: 2 alumnxs por tambor, 1 parche/año por
 * tambor ajustado por uso). Es un cálculo, no se carga ni se aprueba.
 */
class PlanComprasService
{
    public const RATIO_OBJETIVO = 2;

    public const PARCHES_BASE = 1;

    /**
     * @return list<array<string, mixed>>
     */
    public function calcular(?User $user = null): array
    {
        $sedes = Sede::with(['bloques.horarios', 'inventarioItems' => fn ($q) => $q->whereIn('tipo', ['instrumento', 'parche'])])->get();

        $totalSesiones = $sedes->sum(fn ($sede) => $sede->bloques->reduce(fn ($c, $b) => $c + $b->horarios->count(), 0));
        $avgSesiones = $totalSesiones > 0 ? $totalSesiones / max(1, $sedes->count()) : 1;
        if ($avgSesiones <= 0) {
            $avgSesiones = 1;
        }

        // El promedio de uso es de toda la escuela; se devuelven las sedes del alcance.
        if ($user && ! $user->acceso()->puedeGlobal('compras.view')) {
            $sedes = $sedes->filter(fn (Sede $s) => $user->acceso()->puedeEnSede('compras.view', $s->id))->values();
        }

        $datos = [];
        foreach ($sedes as $sede) {
            $alumnos = $sede->alumnosActivos()->count();
            $sesiones = $sede->bloques->reduce(fn ($c, $b) => $c + $b->horarios->count(), 0);
            $instrumentos = $sede->inventarioItems->where('tipo', 'instrumento')->where('propietario_tipo', 'escuela')->count();
            $ratio = $instrumentos > 0 ? ($alumnos > 0 ? round($alumnos / $instrumentos, 2) : 0) : null;
            $necesarios = $alumnos > 0 ? (int) ceil($alumnos / self::RATIO_OBJETIVO) : 0;
            $factorUso = $sesiones > 0 && $avgSesiones > 0 ? $sesiones / $avgSesiones : 1;
            if ($factorUso < 0.5) {
                $factorUso = 0.5;
            }

            $datos[] = [
                'sede' => $sede,
                'alumnos' => $alumnos,
                'sesiones_semana' => $sesiones,
                'instrumentos_escuela' => $instrumentos,
                'ratio_actual' => $ratio,
                'tambores_necesarios' => $necesarios,
                'tambores_faltantes' => max(0, $necesarios - $instrumentos),
                'factor_uso' => round($factorUso, 2),
                'parches_sugeridos' => (int) ceil($instrumentos * self::PARCHES_BASE * $factorUso),
            ];
        }

        return $datos;
    }
}
