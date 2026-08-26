<?php

namespace App\Http\Controllers;

use App\Models\Asistencia;
use App\Models\Bloque;
use App\Models\Cuota;
use App\Models\Gasto;
use App\Models\PagoDetalle;
use App\Models\Profesor;
use App\Models\Sede;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportesController extends Controller
{
    public function index(Request $request)
    {
        $this->assertPuedeVerReportes();

        $mes = $request->filled('mes') ? (int) $request->mes : (int) now()->month;
        $año = $request->filled('año') ? (int) $request->año : (int) now()->year;
        $data = $this->compilarDatos($mes, $año);

        return view('reportes.index', $data);
    }

    public function exportExcel(Request $request)
    {
        $this->assertPuedeVerReportes();

        $mes = $request->filled('mes') ? (int) $request->mes : (int) now()->month;
        $año = $request->filled('año') ? (int) $request->año : (int) now()->year;
        $data = $this->compilarDatos($mes, $año);

        $payload = [
            'meta' => ['mes' => $mes, 'año' => $año],
            'alumnos_profesor' => collect($data['alumnosPorProfesor'])->map(fn ($r) => [
                $r['profesor']->nombre,
                $r['sedes']->join(', '),
                $r['bloques_count'],
                $r['alumnos_count'],
            ])->all(),
            'ingresos_profesor' => collect($data['ingresosPorProfesor'])->map(fn ($r) => [
                $r['profesor']->nombre,
                $r['alumnos_count'],
                round($r['emitido'], 2),
                round($r['cobrado'], 2),
                $r['porcentaje_cobrado'],
            ])->all(),
            'actividad' => collect($data['actividadPorProfesor'])->map(fn ($r) => [
                $r['profesor_nombre'],
                $r['clases_dictadas'],
                $r['alumnos_promedio_presentes'],
                $r['ultimo_bloque']?->nombre ?? '—',
                $r['ultima_fecha'] ?? '—',
            ])->all(),
            'alumnos_bloque' => collect($data['alumnosPorBloque'])->map(fn ($r) => [
                $r['sede']?->nombre ?? '—',
                $r['bloque']->nombre,
                $r['profesor']?->nombre ?? '—',
                $r['alumnos_count'],
                round((float) ($data['ingresosPorBloque'][$r['bloque']->id] ?? 0), 2),
            ])->all(),
            'financiero_sede' => collect($data['resumenFinanciero'])->map(fn ($r) => [
                $r['sede']->nombre,
                round($r['ingresos'], 2),
                round($r['total_gastos'], 2),
                round($r['resultado'], 2),
            ])->all(),
            'global' => [
                ['Ingresos totales', round($data['ingresosTotales'], 2)],
                ['Gastos totales', round($data['gastosTotales'], 2)],
                ['Resultado', round($data['resultadoGlobal'], 2)],
            ],
        ];

        $nombre = sprintf('reportes-%04d-%02d.xlsx', $año, $mes);

        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\ReportesExport($payload), $nombre);
    }

    public function exportPdf(Request $request)
    {
        $this->assertPuedeVerReportes();

        $mes = $request->filled('mes') ? (int) $request->mes : (int) now()->month;
        $año = $request->filled('año') ? (int) $request->año : (int) now()->year;
        $data = $this->compilarDatos($mes, $año);

        return view('reportes.pdf', $data);
    }

    private function assertPuedeVerReportes(): void
    {
        if (! auth()->user()?->puedeVerReportes()) {
            abort(403, 'No tenés acceso a reportes.');
        }
    }

    /**
     * @return list<int>|null null = sin filtro (admin)
     */
    private function sedeScopeIds(): ?array
    {
        return app(\App\Services\AmbitoSedeService::class)->idsPara(auth()->user());
    }

    private function compilarDatos(int $mes, int $año): array
    {
        $sedeScope = $this->sedeScopeIds();

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

        // 1.b) Ingresos por profesor (cuotas emitidas vs cobrado) — filtrable por mes/año
        $cuotasPeriodo = Cuota::query()
            ->with([
                'bloque.profesor',
                'alumnos',
                'bloque.alumnos' => fn ($q) => $q->where('alumnos.activo', true),
            ])
            ->where('año', $año)
            ->where('mes', $mes)
            ->get();

        $totalCobradoPorProfesor = PagoDetalle::query()
            ->join('cuotas', 'pago_detalles.cuota_id', '=', 'cuotas.id')
            ->join('bloques', 'cuotas.bloque_id', '=', 'bloques.id')
            ->selectRaw('bloques.profesor_id as profesor_id, SUM(pago_detalles.monto) as total')
            ->where('cuotas.año', $año)
            ->where('cuotas.mes', $mes)
            ->groupBy('bloques.profesor_id')
            ->pluck('total', 'profesor_id');

        $totalEmitidoPorProfesor = [];
        foreach ($cuotasPeriodo as $cuota) {
            $profesorId = $cuota->bloque?->profesor_id;
            if (! $profesorId) {
                continue;
            }

            $alumnosObjetivo = $cuota->alumnos->isNotEmpty()
                ? $cuota->alumnos->unique('id')->count()
                : ($cuota->bloque?->alumnos?->unique('id')->count() ?? 0);

            $totalEmitidoPorProfesor[$profesorId] = ($totalEmitidoPorProfesor[$profesorId] ?? 0)
                + ((float) $cuota->monto * (int) $alumnosObjetivo);
        }

        $ingresosPorProfesor = $alumnosPorProfesor->map(function (array $row) use ($totalEmitidoPorProfesor, $totalCobradoPorProfesor) {
            $profesor = $row['profesor'];
            $profesorId = $profesor->id;

            $emitido = (float) ($totalEmitidoPorProfesor[$profesorId] ?? 0.0);
            $cobrado = (float) ($totalCobradoPorProfesor[$profesorId] ?? 0.0);
            $porcentaje = $emitido > 0 ? round(($cobrado / $emitido) * 100, 2) : 0.0;

            return [
                'profesor' => $profesor,
                'alumnos_count' => (int) $row['alumnos_count'],
                'emitido' => $emitido,
                'cobrado' => $cobrado,
                'porcentaje_cobrado' => $porcentaje,
            ];
        })->sortByDesc('emitido')->values();

        $añosDisponibles = Cuota::query()
            ->select('año')
            ->distinct()
            ->orderByDesc('año')
            ->pluck('año')
            ->values();

        if ($añosDisponibles->isEmpty()) {
            $añosDisponibles = collect([$año]);
        } elseif (! $añosDisponibles->contains($año)) {
            $añosDisponibles->prepend($año);
        }

        // 1.c) Actividad por profesor (asistencias) — filtrable por mes/año
        $actividadAsistencias = Asistencia::query()
            ->join('bloques', 'asistencias.bloque_id', '=', 'bloques.id')
            ->join('profesores', 'bloques.profesor_id', '=', 'profesores.id')
            ->whereYear('asistencias.fecha', $año)
            ->whereMonth('asistencias.fecha', $mes)
            ->selectRaw('
                profesores.id as profesor_id,
                profesores.nombre as profesor_nombre,
                asistencias.bloque_id as bloque_id,
                MAX(asistencias.fecha) as last_fecha,
                COUNT(DISTINCT asistencias.fecha) as clases_dictadas,
                AVG(CASE WHEN asistencias.presente = 1 THEN 1 ELSE 0 END) as tasa_presencia
            ')
            ->groupBy('profesores.id', 'profesores.nombre', 'asistencias.bloque_id')
            ->get();

        // Promedio de presentes por clase (por bloque): presentes/clases_distintas
        $presentesPorBloque = Asistencia::query()
            ->whereYear('fecha', $año)
            ->whereMonth('fecha', $mes)
            ->selectRaw('bloque_id, COUNT(DISTINCT fecha) as clases, SUM(CASE WHEN presente = 1 THEN 1 ELSE 0 END) as presentes')
            ->groupBy('bloque_id')
            ->get()
            ->keyBy('bloque_id');

        $bloquesMeta = Bloque::query()
            ->with(['profesor'])
            ->where('activo', true)
            ->get()
            ->keyBy('id');

        $actividadPorProfesor = $actividadAsistencias
            ->groupBy('profesor_id')
            ->map(function ($rows, $profesorId) use ($presentesPorBloque, $bloquesMeta) {
                $profesorNombre = $rows->first()->profesor_nombre ?? '—';

                $clasesDictadas = (int) $rows->sum('clases_dictadas');

                $promedios = $rows->map(function ($r) use ($presentesPorBloque) {
                    $meta = $presentesPorBloque->get($r->bloque_id);
                    if (! $meta || (int) $meta->clases === 0) {
                        return null;
                    }

                    return (float) $meta->presentes / (int) $meta->clases;
                })->filter();

                $alumnosPromedio = $promedios->isNotEmpty()
                    ? round($promedios->avg(), 2)
                    : 0.0;

                $ultimoBloqueRow = $rows->sortByDesc('last_fecha')->first();
                $ultimoBloque = $ultimoBloqueRow
                    ? ($bloquesMeta->get($ultimoBloqueRow->bloque_id) ?? null)
                    : null;

                return [
                    'profesor_id' => (int) $profesorId,
                    'profesor_nombre' => $profesorNombre,
                    'clases_dictadas' => $clasesDictadas,
                    'alumnos_promedio_presentes' => $alumnosPromedio,
                    'ultimo_bloque' => $ultimoBloque,
                    'ultima_fecha' => $ultimoBloqueRow?->last_fecha,
                ];
            })
            ->sortByDesc('clases_dictadas')
            ->values();

        // 2) Alumnos por bloque
        $bloquesQ = Bloque::with(['sede', 'profesor', 'alumnos' => fn ($q) => $q->where('alumnos.activo', true)])
            ->where('activo', true)
            ->orderBy('sede_id')
            ->orderBy('nombre');
        if ($sedeScope !== null) {
            $bloquesQ->whereIn('sede_id', $sedeScope);
        }
        $bloques = $bloquesQ->get();

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
            if (! isset($gastosPorSede[$sid])) {
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
        $sedesQ = Sede::with(['gastos'])->orderBy('nombre');
        if ($sedeScope !== null) {
            $sedesQ->whereIn('id', $sedeScope);
        }
        $sedes = $sedesQ->get();
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

        // 7) Inversión total vs recuperado
        if ($sedeScope !== null) {
            $ingresosTotales = (float) collect($resumenFinanciero)->sum('ingresos');
            $gastosTotales = (float) collect($resumenFinanciero)->sum('total_gastos');
        } else {
            $ingresosTotales = (float) PagoDetalle::sum('monto');
            $gastosTotales = (float) Gasto::sum('monto');
        }
        $resultadoGlobal = $ingresosTotales - $gastosTotales;

        // Scope por sede: filtrar bloques / profesores
        if ($sedeScope !== null) {
            $alumnosPorBloque = $alumnosPorBloque->filter(
                fn ($r) => in_array((int) ($r['sede']?->id ?? 0), $sedeScope, true)
            )->values();
            $alumnosPorProfesor = $alumnosPorProfesor->filter(function ($r) use ($sedeScope) {
                $sedeNombres = Sede::query()->whereIn('id', $sedeScope)->pluck('nombre')->all();

                return $r['sedes']->intersect($sedeNombres)->isNotEmpty()
                    || $r['sedes']->isEmpty();
            })->values();
            // Mejor: filtrar por bloques de la sede
            $profIdsEnSede = Bloque::query()->whereIn('sede_id', $sedeScope)->pluck('profesor_id')->filter()->unique()->all();
            $alumnosPorProfesor = $alumnosPorProfesor->filter(
                fn ($r) => in_array((int) $r['profesor']->id, $profIdsEnSede, true)
            )->values();
            $ingresosPorProfesor = $ingresosPorProfesor->filter(
                fn ($r) => in_array((int) $r['profesor']->id, $profIdsEnSede, true)
            )->values();
            $actividadPorProfesor = $actividadPorProfesor->filter(
                fn ($r) => in_array((int) $r['profesor_id'], $profIdsEnSede, true)
            )->values();
        }

        return [
            'mes' => $mes,
            'año' => $año,
            'añosDisponibles' => $añosDisponibles,
            'ingresosPorProfesor' => $ingresosPorProfesor,
            'actividadPorProfesor' => $actividadPorProfesor,
            'alumnosPorProfesor' => $alumnosPorProfesor,
            'alumnosPorBloque' => $alumnosPorBloque,
            'ingresosPorBloque' => $ingresosPorBloque,
            'resumenFinanciero' => $resumenFinanciero,
            'ingresosTotales' => $ingresosTotales,
            'gastosTotales' => $gastosTotales,
            'resultadoGlobal' => $resultadoGlobal,
            'sedeScope' => $sedeScope,
        ];
    }

    public function profesores()
    {
        $this->assertPuedeVerReportes();
        $sedeScope = $this->sedeScopeIds();

        $profesores = Profesor::with(['bloques.alumnos' => fn ($q) => $q->where('alumnos.activo', true), 'bloques.sede'])
            ->where('activo', true)
            ->get();

        $alumnosPorProfesor = $profesores->map(function (Profesor $profesor) use ($sedeScope) {
            $bloques = $profesor->bloques;
            if ($sedeScope !== null) {
                $bloques = $bloques->filter(fn (Bloque $b) => in_array((int) $b->sede_id, $sedeScope, true));
            }
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
        })->sortByDesc('alumnos_count')->values();

        return view('reportes.profesores', compact('alumnosPorProfesor'));
    }
}
