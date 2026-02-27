<?php

namespace App\Http\Controllers;

use App\Models\Alumno;
use App\Models\Bloque;
use App\Models\Gasto;
use App\Models\PagoDetalle;
use App\Models\Profesor;
use App\Models\Sede;
use Carbon\Carbon;

class ReportesController extends Controller
{
    public function index()
    {
        // 1) Alumnos por profesor
        $profesores = Profesor::with(['bloques.alumnos' => fn ($q) => $q->where('alumnos.activo', true)])
            ->where('activo', true)
            ->get();

        $alumnosPorProfesor = $profesores->map(function (Profesor $profesor) {
            $bloques = $profesor->bloques;
            $alumnosIds = $bloques
                ->flatMap(fn (Bloque $b) => $b->alumnos->pluck('id'))
                ->unique()
                ->values();

            return [
                'profesor' => $profesor,
                'sedes' => $bloques->pluck('sede.nombre')->unique()->filter()->values(),
                'bloques_count' => $bloques->count(),
                'alumnos_count' => $alumnosIds->count(),
            ];
        });

        // 2) Alumnos por bloque
        $bloques = Bloque::with(['sede', 'profesor', 'alumnos' => fn ($q) => $q->where('alumnos.activo', true)])
            ->where('activo', true)
            ->orderBy('sede_id')
            ->orderBy('nombre')
            ->get();

        $alumnosPorBloque = $bloques->map(function (Bloque $b) {
            return [
                'bloque' => $b,
                'sede' => $b->sede,
                'profesor' => $b->profesor,
                'alumnos_count' => $b->alumnos->unique('id')->count(),
            ];
        });

        // 3) Ingresos por sede / bloque (a partir de PagoDetalle)
        $ingresosPorSede = PagoDetalle::query()
            ->join('alumnos', 'pago_detalles.alumno_id', '=', 'alumnos.id')
            ->selectRaw('alumnos.sede_id, SUM(pago_detalles.monto) as total')
            ->groupBy('alumnos.sede_id')
            ->pluck('total', 'alumnos.sede_id');

        $ingresosPorBloque = PagoDetalle::query()
            ->join('alumnos', 'pago_detalles.alumno_id', '=', 'alumnos.id')
            ->selectRaw('alumnos.bloque_id, SUM(pago_detalles.monto) as total')
            ->groupBy('alumnos.bloque_id')
            ->pluck('total', 'alumnos.bloque_id');

        // 4) Gastos por sede
        $gastosBase = Gasto::selectRaw('sede_id, tipo, subtipo, SUM(monto) as total')
            ->groupBy('sede_id', 'tipo', 'subtipo')
            ->get();

        $gastosPorSede = [];
        foreach ($gastosBase as $g) {
            $sid = $g->sede_id;
            if (!isset($gastosPorSede[$sid])) {
                $gastosPorSede[$sid] = [
                    'sueldos' => 0,
                    'alquiler' => 0,
                    'luz' => 0,
                    'agua' => 0,
                    'reparaciones_edilicias' => 0,
                    'reparaciones_tambores' => 0,
                    'insumos' => 0,
                    'servicios_externos' => 0,
                    'otros' => 0,
                ];
            }

            $total = (float) $g->total;
            switch ($g->tipo) {
                case 'sueldo':
                    $gastosPorSede[$sid]['sueldos'] += $total;
                    break;
                case 'alquiler':
                    $gastosPorSede[$sid]['alquiler'] += $total;
                    break;
                case 'servicio':
                    if ($g->subtipo === 'luz') {
                        $gastosPorSede[$sid]['luz'] += $total;
                    } elseif ($g->subtipo === 'agua') {
                        $gastosPorSede[$sid]['agua'] += $total;
                    } else {
                        $gastosPorSede[$sid]['servicios_externos'] += $total;
                    }
                    break;
                case 'reparacion':
                    if ($g->subtipo === 'tambores') {
                        $gastosPorSede[$sid]['reparaciones_tambores'] += $total;
                    } else {
                        $gastosPorSede[$sid]['reparaciones_edilicias'] += $total;
                    }
                    break;
                case 'insumo':
                    $gastosPorSede[$sid]['insumos'] += $total;
                    break;
                case 'servicio_externo':
                    $gastosPorSede[$sid]['servicios_externos'] += $total;
                    break;
                default:
                    $gastosPorSede[$sid]['otros'] += $total;
                    break;
            }
        }

        // 5) Frecuencia de reposición de insumos por sede (a partir de gastos tipo insumo)
        $frecuenciasInsumos = [];
        $insumosPorSede = Gasto::where('tipo', 'insumo')
            ->orderBy('sede_id')
            ->orderBy('fecha')
            ->get()
            ->groupBy('sede_id');

        foreach ($insumosPorSede as $sedeId => $gastosInsumos) {
            if ($gastosInsumos->count() < 2) {
                $frecuenciasInsumos[$sedeId] = null;
                continue;
            }
            $diffs = [];
            for ($i = 1; $i < $gastosInsumos->count(); $i++) {
                $prev = Carbon::parse($gastosInsumos[$i - 1]->fecha);
                $cur = Carbon::parse($gastosInsumos[$i]->fecha);
                $diffs[] = $prev->diffInDays($cur);
            }
            $frecuenciasInsumos[$sedeId] = (int) round(array_sum($diffs) / max(count($diffs), 1));
        }

        // 6) Armar resumen por sede (ingresos vs egresos, propiedad sede, etc.)
        $sedes = Sede::with(['gastos'])->orderBy('nombre')->get();
        $resumenFinanciero = [];

        foreach ($sedes as $sede) {
            $sid = $sede->id;
            $ingresos = (float) ($ingresosPorSede[$sid] ?? 0.0);
            $g = $gastosPorSede[$sid] ?? [
                'sueldos' => 0,
                'alquiler' => 0,
                'luz' => 0,
                'agua' => 0,
                'reparaciones_edilicias' => 0,
                'reparaciones_tambores' => 0,
                'insumos' => 0,
                'servicios_externos' => 0,
                'otros' => 0,
            ];
            $totalGastos = array_sum($g);
            $resultado = $ingresos - $totalGastos;

            $resumenFinanciero[] = [
                'sede' => $sede,
                'ingresos' => $ingresos,
                'gastos_detalle' => $g,
                'total_gastos' => $totalGastos,
                'resultado' => $resultado,
                'frecuencia_insumos_dias' => $frecuenciasInsumos[$sid] ?? null,
            ];
        }

        // 7) Inversión total vs recuperado (global)
        $ingresosTotales = (float) PagoDetalle::sum('monto');
        $gastosTotales = (float) Gasto::sum('monto');
        $resultadoGlobal = $ingresosTotales - $gastosTotales;

        return view('reportes.index', [
            'alumnosPorProfesor' => $alumnosPorProfesor,
            'alumnosPorBloque' => $alumnosPorBloque,
            'ingresosPorBloque' => $ingresosPorBloque,
            'resumenFinanciero' => $resumenFinanciero,
            'ingresosTotales' => $ingresosTotales,
            'gastosTotales' => $gastosTotales,
            'resultadoGlobal' => $resultadoGlobal,
        ]);
    }
}

