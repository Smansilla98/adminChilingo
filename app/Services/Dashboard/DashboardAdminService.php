<?php

namespace App\Services\Dashboard;

use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\Bloque;
use App\Models\BloqueHorario;
use App\Models\ComprobanteCuotaAlumno;
use App\Models\Cuota;
use App\Models\Evento;
use App\Models\Gasto;
use App\Models\Pago;
use App\Models\Profesor;
use App\Models\Sede;
use App\Models\User;
use App\Services\AmbitoSedeService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Datos del panel de administración (KPIs, cuotas del mes, asistencias de la semana, recaudación).
 * Lo llama DashboardController; la vista recibe exactamente las mismas variables que antes.
 */
class DashboardAdminService
{
    public function __construct(private readonly CobrosPendientesService $cobros) {}

    /**
     * @return array<string, mixed>
     */
    public function armar(User $user, AmbitoSedeService $ambito): array
    {
        $filtroSedes = $ambito->idsPara($user);
        $dashboardAmbito = $ambito->etiqueta($user);

        $mesActual = (int) now()->month;
        $anioActual = (int) now()->year;

        $cacheKey = 'dash.kpi.'.$user->id.'.'.md5(json_encode($filtroSedes));
        $kpis = Cache::remember($cacheKey, 90, function () use ($filtroSedes, $anioActual, $mesActual, $ambito) {
            $alumnosQ = Alumno::query()->where('activo', true);
            $bloquesQ = Bloque::query()->where('activo', true);
            if ($filtroSedes !== null) {
                $ambito->aplicarAlumnos($alumnosQ, $filtroSedes);
                $ambito->aplicarBloques($bloquesQ, $filtroSedes);
            }
            $asistQ = Asistencia::query()->whereYear('fecha', $anioActual)->whereMonth('fecha', $mesActual);
            if ($filtroSedes !== null) {
                $asistQ->whereHas('bloque', function ($q) use ($ambito, $filtroSedes) {
                    $ambito->aplicarBloques($q, $filtroSedes);
                });
            }

            return [
                'alumnosActivos' => $alumnosQ->count(),
                'bloquesActivos' => $bloquesQ->count(),
                'alumnosNuevosMes' => (clone $alumnosQ)->whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->count(),
                'sedesActivasEnBloques' => (int) (clone $bloquesQ)->distinct()->count('sede_id'),
                'asistenciasMes' => (int) $asistQ->count(),
            ];
        });
        $alumnosActivos = $kpis['alumnosActivos'];
        $bloquesActivos = $kpis['bloquesActivos'];
        $alumnosNuevosMes = $kpis['alumnosNuevosMes'];
        $sedesActivasEnBloques = $kpis['sedesActivasEnBloques'];
        $asistenciasMes = $kpis['asistenciasMes'];

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

        // Profesores (top 8) por cantidad de alumnos activos en sus bloques
        $profBaseQ = Profesor::query()
            ->where('activo', true)
            ->with(['bloques' => function ($q) use ($filtroSedes, $ambito) {
                $q->where('activo', true)->with('sede');
                if ($filtroSedes !== null) {
                    $ambito->aplicarBloques($q, $filtroSedes);
                }
            }])
            ->withCount(['bloques as bloques_count' => function ($q) use ($filtroSedes, $ambito) {
                $q->where('activo', true);
                if ($filtroSedes !== null) {
                    $ambito->aplicarBloques($q, $filtroSedes);
                }
            }]);
        if ($filtroSedes !== null) {
            $profBaseQ->whereHas('bloques', function ($q) use ($filtroSedes, $ambito) {
                $q->where('activo', true);
                $ambito->aplicarBloques($q, $filtroSedes);
            });
        }
        $profBase = $profBaseQ
            ->get()
            ->map(function (Profesor $p) {
                $p->alumnos_count = (int) ($p->bloques_count ?? $p->bloques->count());
                $p->sedes_str = $p->bloques->pluck('sede.nombre')->unique()->filter()->implode(' · ');
                $p->initials = collect(preg_split('/\s+/', trim($p->nombre ?? '')))
                    ->filter()
                    ->take(2)
                    ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
                    ->join('');
                $colors = ['av-orange', 'av-blue', 'av-green', 'av-purple', 'av-amber'];
                $p->avatar_class = $colors[abs(crc32((string) $p->id)) % count($colors)];

                return $p;
            })
            ->sortByDesc('alumnos_count')
            ->values()
            ->take(8);

        $maxAlumnosProfesor = (int) ($profBase->max('alumnos_count') ?: 1);

        // Asistencias de la semana (cards por bloque con al menos 1 horario)
        $inicioSemana = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $finSemana = Carbon::now()->endOfWeek(Carbon::SUNDAY);

        $horarios = BloqueHorario::query()
            ->with(['bloque' => function ($q) use ($filtroSedes, $ambito) {
                $q->where('activo', true)
                    ->with(['sede', 'profesor'])
                    ->withCount(['alumnos as alumnos_activos_count' => fn ($qa) => $qa->where('alumnos.activo', true)]);
                if ($filtroSedes !== null) {
                    $ambito->aplicarBloques($q, $filtroSedes);
                }
            }])
            ->get()
            ->filter(fn (BloqueHorario $h) => (bool) $h->bloque)
            ->groupBy('bloque_id')
            ->map(fn ($rows) => $rows->sortBy(['dia_semana', 'hora_inicio'])->first())
            ->values();

        $bloqueIds = $horarios->pluck('bloque_id')->unique()->values();
        $hoy = Carbon::today();

        // Asistencias de la semana (agrupadas por bloque + día para cruzar con fecha de clase)
        $asistPorBloqueFecha = Asistencia::query()
            ->whereBetween('fecha', [$inicioSemana->toDateString(), $finSemana->toDateString()])
            ->whereIn('bloque_id', $bloqueIds->all() ?: [0])
            ->get(['bloque_id', 'fecha', 'presente', 'tipo_asistencia'])
            ->groupBy(fn (Asistencia $a) => $a->bloque_id.'|'.$a->fecha->format('Y-m-d'))
            ->map(function ($filas) {
                return (object) [
                    'bloque_id' => $filas->first()->bloque_id,
                    'fecha_dia' => $filas->first()->fecha->format('Y-m-d'),
                    'total_reg' => $filas->count(),
                    'presentes' => $filas->filter(
                        fn (Asistencia $a) => $a->presente || in_array($a->tipo_asistencia, ['presente', 'tarde'], true)
                    )->count(),
                ];
            });

        $bloquesSemanales = $horarios->map(function (BloqueHorario $h) use ($inicioSemana, $asistPorBloqueFecha, $hoy) {
            $bloque = $h->bloque;
            $fechaClase = $inicioSemana->copy()->addDays(max(0, ((int) $h->dia_semana) - 1))->startOfDay();

            $totalAlumnos = $bloque ? (int) ($bloque->alumnos_activos_count ?? 0) : 0;

            $agg = $this->resolverAsistenciaClaseSemana($asistPorBloqueFecha, (int) $bloque->id, $fechaClase, $inicioSemana, $hoy);
            $regTotal = (int) ($agg->total_reg ?? 0);
            $presentes = (int) ($agg->presentes ?? 0);

            if ($fechaClase->gt($hoy)) {
                $estado = 'Próxima';
                $badge = 'badge-pend';
            } elseif ($regTotal > 0 && ($totalAlumnos === 0 || $regTotal >= $totalAlumnos)) {
                $estado = 'Tomada';
                $badge = 'badge-ok';
            } elseif ($regTotal > 0) {
                $estado = 'Incompleta';
                $badge = 'badge-warn';
            } else {
                $estado = 'Pendiente';
                $badge = 'badge-pend';
            }

            $pct = $totalAlumnos > 0 ? round(($presentes / $totalAlumnos) * 100) : 0;

            return [
                'bloque' => $bloque,
                'sede' => $bloque?->sede,
                'profesor' => $bloque?->profesor,
                'horario' => $h,
                'fecha_clase' => $fechaClase,
                'estado' => $estado,
                'badge_class' => $badge,
                'presentes' => $presentes,
                'total_alumnos' => $totalAlumnos,
                'pct' => $pct,
            ];
        })->sortBy(function ($row) {
            return ($row['horario']->dia_semana * 10000) + (int) str_replace(':', '', substr((string) $row['horario']->hora_inicio, 0, 5));
        })->values();

        // Cobros pendientes del mes (misma lógica de alcance / cuota_alumno que el contador superior)
        $cuotasPendientesList = $this->cobros->armarListadoCobrosPendientes($cuotasMes, $hoy, 8, $filtroSedes, $ambito);

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

        $eventosQ = Evento::query()->proximos()->orderBy('fecha');
        if ($filtroSedes !== null) {
            $ambito->aplicarEventos($eventosQ, $filtroSedes);
        }
        $proximosEventos = (clone $eventosQ)->limit(5)->get();
        $proximosEventosCount = (int) (clone $eventosQ)->count();

        $comprobantesPendientesCount = 0;
        $comprobantesPendientesList = collect();
        if (Schema::hasTable('comprobantes_cuota_alumnos')) {
            $compQ = ComprobanteCuotaAlumno::query()->where('estado', 'pendiente');
            if ($filtroSedes !== null) {
                $ambito->aplicarComprobantes($compQ, $filtroSedes);
            }
            $comprobantesPendientesCount = (int) (clone $compQ)->count();
            $comprobantesPendientesList = (clone $compQ)
                ->with('alumno')
                ->latest()
                ->limit(5)
                ->get();
        }

        $bloquesCupoQ = Bloque::query()
            ->where('activo', true)
            ->with(['sede', 'profesor'])
            ->withCount(['alumnos as alumnos_activos_count' => fn ($q) => $q->where('alumnos.activo', true)])
            ->orderBy('nombre');
        if ($filtroSedes !== null) {
            $ambito->aplicarBloques($bloquesCupoQ, $filtroSedes);
        }
        $bloquesCupo = $bloquesCupoQ->take(12)->get();

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

        $sedesChartQ = Sede::query()->orderBy('nombre');
        if ($filtroSedes !== null) {
            $ambito->aplicarSedesCatalogo($sedesChartQ, $filtroSedes);
        }
        $alumnosPorSedeChart = $sedesChartQ
            ->get()
            ->map(function (Sede $sede) {
                $total = Alumno::query()
                    ->where('activo', true)
                    ->where(function ($q) use ($sede) {
                        $q->where('sede_id', $sede->id)
                            ->orWhereHas('bloques', fn ($b) => $b->where('bloques.sede_id', $sede->id));
                    })
                    ->count();

                return ['nombre' => $sede->nombre, 'total' => $total];
            })
            ->filter(fn ($r) => $r['total'] > 0)
            ->values();

        $adminNombre = trim(auth()->user()->name ?: auth()->user()->username ?: '');

        $atencionHoy = collect();
        if ($comprobantesPendientesCount > 0) {
            $atencionHoy->push([
                'href' => route('comprobantes-cuota-alumnos.index', ['estado' => 'pendiente']),
                'title' => $comprobantesPendientesCount.' comprobantes sin revisar',
                'hint' => 'Cobranza de hoy',
            ]);
        }
        if ($cuotasPendientes > 0) {
            $atencionHoy->push([
                'href' => route('cuotas.index'),
                'title' => $cuotasPendientes.' cuotas del mes aún abiertas',
                'hint' => 'Finanzas',
            ]);
        }
        $evHoy = $proximosEventos->first();
        if ($evHoy && $evHoy->fecha->isToday()) {
            $atencionHoy->push([
                'href' => route('eventos.index'),
                'title' => 'Hoy: '.$evHoy->titulo,
                'hint' => 'Agenda',
            ]);
        } elseif ($evHoy !== null && $proximosEventosCount > 0) {
            $atencionHoy->push([
                'href' => route('eventos.index'),
                'title' => 'Próximo: '.$evHoy->titulo,
                'hint' => $evHoy->fecha->locale('es')->translatedFormat('d M'),
            ]);
        }

        return compact(
            'alumnosActivos',
            'bloquesActivos',
            'alumnosNuevosMes',
            'sedesActivasEnBloques',
            'cuotasTotal',
            'cuotasAbonadas',
            'cuotasPendientes',
            'pctAbonadas',
            'pctPendientes',
            'profBase',
            'maxAlumnosProfesor',
            'bloquesSemanales',
            'cuotasPendientesList',
            'recaudacion',
            'cobradoMes',
            'proximosEventos',
            'proximosEventosCount',
            'comprobantesPendientesCount',
            'comprobantesPendientesList',
            'bloquesCupo',
            'chartLabels',
            'chartIngresos',
            'chartGastos',
            'alumnosPorSedeChart',
            'asistenciasMes',
            'adminNombre',
            'dashboardAmbito',
            'atencionHoy'
        );
    }

    /**
     * Panel sin datos: se usa si faltan tablas por migraciones pendientes.
     *
     * @return array<string, mixed>
     */
    public static function vacio(): array
    {
        return [
            'alumnosActivos' => 0,
            'bloquesActivos' => 0,
            'alumnosNuevosMes' => 0,
            'sedesActivasEnBloques' => 0,
            'cuotasTotal' => 0,
            'cuotasAbonadas' => 0,
            'cuotasPendientes' => 0,
            'pctAbonadas' => 0,
            'pctPendientes' => 0,
            'profBase' => collect(),
            'maxAlumnosProfesor' => 1,
            'bloquesSemanales' => collect(),
            'cuotasPendientesList' => collect(),
            'recaudacion' => collect([0, 0, 0, 0, 0, 0]),
            'cobradoMes' => 0,
            'proximosEventos' => collect(),
            'proximosEventosCount' => 0,
            'comprobantesPendientesCount' => 0,
            'comprobantesPendientesList' => collect(),
            'bloquesCupo' => collect(),
            'chartLabels' => [],
            'chartIngresos' => [],
            'chartGastos' => [],
            'alumnosPorSedeChart' => collect(),
            'asistenciasMes' => 0,
            'adminNombre' => '',
            'dashboardAmbito' => 'Vista general de la escuela',
            'atencionHoy' => collect(),
        ];
    }

    /**
     * Busca registros de asistencia para el día de clase de esta semana (fecha exacta de la clase).
     */
    private function resolverAsistenciaClaseSemana(
        $asistPorBloqueFecha,
        int $bloqueId,
        Carbon $fechaClase,
        Carbon $inicioSemana,
        Carbon $hoy
    ): object {
        $claveExacta = $bloqueId.'|'.$fechaClase->toDateString();
        if ($asistPorBloqueFecha->has($claveExacta)) {
            return $asistPorBloqueFecha->get($claveExacta);
        }

        // Si la clase ya pasó: aceptar asistencia cargada en otra fecha de la misma semana (p. ej. fecha del formulario distinta al viernes de clase)
        if ($fechaClase->lte($hoy)) {
            $candidatos = $asistPorBloqueFecha->filter(function ($row) use ($bloqueId, $fechaClase, $inicioSemana) {
                if ((int) $row->bloque_id !== $bloqueId) {
                    return false;
                }
                $f = Carbon::parse($row->fecha_dia)->startOfDay();
                $desde = $inicioSemana->copy()->startOfDay();

                return $f->gte($desde) && $f->lte($fechaClase);
            })->sortByDesc('fecha_dia');

            if ($candidatos->isNotEmpty()) {
                return $candidatos->first();
            }
        }

        return (object) ['total_reg' => 0, 'presentes' => 0];
    }
}
