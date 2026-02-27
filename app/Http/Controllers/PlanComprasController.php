<?php

namespace App\Http\Controllers;

use App\Models\Sede;
use App\Models\InventarioItem;

class PlanComprasController extends Controller
{
    /**
     * Muestra, por sede, la trazabilidad básica y una sugerencia de compra
     * de instrumentos y parches según alumnos, carga horaria y stock.
     */
    public function index()
    {
        $sedes = Sede::with([
            'bloques.horarios',
            'inventarioItems' => function ($q) {
                $q->whereIn('tipo', ['instrumento', 'parche']);
            },
        ])->get();

        $datos = [];
        $totalSesiones = 0;

        foreach ($sedes as $sede) {
            $sesiones = $sede->bloques->reduce(
                fn ($carry, $bloque) => $carry + $bloque->horarios->count(),
                0
            );
            $totalSesiones += $sesiones;
        }

        $sedesCount = max(1, $sedes->count());
        $avgSesiones = $totalSesiones > 0 ? $totalSesiones / $sedesCount : 1;
        if ($avgSesiones <= 0) {
            $avgSesiones = 1;
        }

        $ratioObjetivo = 2; // 2 alumnxs por tambor como referencia
        $parchesPorTamborBase = 1; // 1 parche/año por tambor como base

        foreach ($sedes as $sede) {
            $alumnos = $sede->alumnosActivos()->count();
            $sesiones = $sede->bloques->reduce(
                fn ($carry, $bloque) => $carry + $bloque->horarios->count(),
                0
            );

            $instrumentosEscuela = $sede->inventarioItems
                ->where('tipo', 'instrumento')
                ->where('propietario_tipo', 'escuela')
                ->count();

            $ratioActual = $instrumentosEscuela > 0
                ? ($alumnos > 0 ? round($alumnos / $instrumentosEscuela, 2) : 0)
                : null;

            $tamboresNecesarios = $alumnos > 0
                ? (int) ceil($alumnos / $ratioObjetivo)
                : 0;

            $tamboresFaltantes = max(0, $tamboresNecesarios - $instrumentosEscuela);

            $factorUso = $sesiones > 0 && $avgSesiones > 0 ? $sesiones / $avgSesiones : 1;
            if ($factorUso < 0.5) {
                $factorUso = 0.5;
            }

            $parchesSugeridos = (int) ceil($instrumentosEscuela * $parchesPorTamborBase * $factorUso);

            $datos[] = [
                'sede' => $sede,
                'alumnos' => $alumnos,
                'sesiones_semana' => $sesiones,
                'instrumentos_escuela' => $instrumentosEscuela,
                'ratio_actual' => $ratioActual,
                'tambores_necesarios' => $tamboresNecesarios,
                'tambores_faltantes' => $tamboresFaltantes,
                'factor_uso' => round($factorUso, 2),
                'parches_sugeridos' => $parchesSugeridos,
            ];
        }

        return view('plan-compras.index', [
            'sedesDatos' => $datos,
            'ratioObjetivo' => $ratioObjetivo,
            'parchesBase' => $parchesPorTamborBase,
        ]);
    }
}

