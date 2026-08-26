<?php

namespace App\Http\Controllers;

use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\Bloque;
use App\Models\BloqueHorario;
use App\Models\ComprobanteCuotaAlumno;
use App\Models\Cuota;
use App\Models\Evento;
use App\Models\Gasto;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\Profesor;
use App\Models\Sede;
use App\Services\AmbitoSedeService;
use App\Services\EspacioAlumnoService;
use App\Services\JornadaDocenteService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index(AmbitoSedeService $ambito)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        // Si es alumno (sin rol docente/gestión), espacio simple
        if ($user->isAlumno() && ! $user->isProfesor() && ! $user->isAdmin() && ! $user->puedeGestionarOperativo()) {
            return $this->dashboardAlumno();
        }

        // Si es profesor (sin panel de gestión), dashboard limitado
        if ($user->isProfesor() && ! $user->isAdmin() && ! $user->puedeGestionarOperativo()) {
            return $this->dashboardProfesor();
        }

        // Dashboard Admin (protegido si faltan tablas por migraciones pendientes)
        try {
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
                $cuotasTotal += $this->contarObjetivoCuota($c, $filtroSedes, $ambito);
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
            $cuotasPendientesList = $this->armarListadoCobrosPendientes($cuotasMes, $hoy, 8, $filtroSedes, $ambito);

            // Recaudación últimas 6 semanas (sum monto_total por fecha_pago)
            $recaudacion = collect(range(5, 0))->map(function ($i) use ($filtroSedes, $ambito) {
                $inicio = Carbon::now()->startOfWeek(Carbon::MONDAY)->subWeeks($i);
                $fin = $inicio->copy()->endOfWeek(Carbon::SUNDAY);
                $q = Pago::query()->whereBetween('fecha_pago', [$inicio->toDateString(), $fin->toDateString()]);
                if ($filtroSedes !== null) {
                    $ambito->aplicarPagos($q, $filtroSedes);
                }

                return (float) $q->sum('monto_total');
            });

            $cobradoMesQ = Pago::query()
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
                $ingQ = Pago::query()
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
            if (($comprobantesPendientesCount ?? 0) > 0) {
                $atencionHoy->push([
                    'href' => route('comprobantes-cuota-alumnos.index', ['estado' => 'pendiente']),
                    'title' => $comprobantesPendientesCount.' comprobantes sin revisar',
                    'hint' => 'Cobranza de hoy',
                ]);
            }
            if (($cuotasPendientes ?? 0) > 0) {
                $atencionHoy->push([
                    'href' => route('cuotas.index'),
                    'title' => $cuotasPendientes.' cuotas del mes aún abiertas',
                    'hint' => 'Finanzas',
                ]);
            }
            $evHoy = ($proximosEventos ?? collect())->first();
            if ($evHoy && $evHoy->fecha && $evHoy->fecha->isToday()) {
                $atencionHoy->push([
                    'href' => route('eventos.index'),
                    'title' => 'Hoy: '.$evHoy->titulo,
                    'hint' => 'Agenda',
                ]);
            } elseif (($proximosEventosCount ?? 0) > 0 && $evHoy) {
                $atencionHoy->push([
                    'href' => route('eventos.index'),
                    'title' => 'Próximo: '.$evHoy->titulo,
                    'hint' => $evHoy->fecha?->locale('es')->translatedFormat('d M'),
                ]);
            }

            return view('dashboard.index', compact(
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
            ));
        } catch (\Illuminate\Database\QueryException $e) {
            return view('dashboard.index', [
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
            ]);
        }
    }

    private function dashboardProfesor()
    {
        $user = auth()->user();
        $bloques = collect();
        $proximosEventos = collect();
        $comprobantesCuotaPendientes = 0;

        try {
            $profesor = $user->profesor;

            if ($profesor) {
                $bloques = $profesor->bloquesActivos()->with(['sede', 'alumnos', 'horarios'])->get();
                $proximosEventos = Evento::where('profesor_id', $profesor->id)
                    ->proximos()
                    ->limit(5)
                    ->get();
                if (Schema::hasTable('comprobantes_cuota_alumnos')) {
                    $ids = $profesor->bloqueIdsDondeParticipa()->all();
                    $comprobantesCuotaPendientes = (int) ComprobanteCuotaAlumno::query()
                        ->where('estado', 'pendiente')
                        ->whereHas('items', fn ($q) => $q->whereIn('bloque_id', $ids !== [] ? $ids : [0]))
                        ->count();
                }
            }
        } catch (\Illuminate\Database\QueryException $e) {
            // Tabla profesors/profesores u otras no existen (migraciones pendientes)
        } catch (\Throwable $e) {
            // Cualquier otro fallo: mostrar dashboard vacío
        }

        $jornada = app(JornadaDocenteService::class)->armar($user, $bloques);
        $bloquesHoy = $jornada['bloquesHoy'];
        $pendientesAsistencia = $jornada['pendientesAsistencia'];
        $proximosPasos = $jornada['proximosPasos'];

        return view('dashboard.profesor', compact(
            'bloques',
            'bloquesHoy',
            'pendientesAsistencia',
            'proximosPasos',
            'proximosEventos',
            'comprobantesCuotaPendientes'
        ));
    }

    private function dashboardAlumno()
    {
        $user = auth()->user();
        $alumno = $user->alumno;
        if ($alumno) {
            $alumno->loadMissing('sede');
        }

        $espacio = app(EspacioAlumnoService::class)->armar($alumno);

        return view('dashboard.alumno', array_merge(compact('alumno'), $espacio));
    }

    /**
     * Listado lateral de cobros pendientes (alumno + cuota del mes sin línea en pago_detalles).
     *
     * @param  \Illuminate\Support\Collection<int, Cuota>  $cuotasMes
     * @param  list<int>|null  $filtroSedes
     */
    private function armarListadoCobrosPendientes(
        $cuotasMes,
        Carbon $hoy,
        int $limit = 8,
        ?array $filtroSedes = null,
        ?AmbitoSedeService $ambito = null
    ): \Illuminate\Support\Collection {
        if ($cuotasMes->isEmpty() || ! Schema::hasTable('pago_detalles')) {
            return collect();
        }

        $cuotaIds = $cuotasMes->pluck('id')->filter()->values();
        $pagados = PagoDetalle::query()
            ->whereIn('cuota_id', $cuotaIds)
            ->get(['alumno_id', 'cuota_id'])
            ->mapWithKeys(fn (PagoDetalle $pd) => [$pd->alumno_id.'-'.$pd->cuota_id => true]);

        $mesLabel = now()->locale('es')->translatedFormat('M Y');
        $filas = collect();

        foreach ($cuotasMes as $cuota) {
            foreach ($this->alumnosObjetivoCuota($cuota, $filtroSedes, $ambito) as $alumno) {
                if ($pagados->has($alumno->id.'-'.$cuota->id)) {
                    continue;
                }

                $fv = $cuota->fecha_vencimiento;
                $isVencida = $fv ? $fv->copy()->startOfDay()->lt($hoy->copy()->subDays(5)) : false;

                $filas->push([
                    'alumno' => $alumno->nombre_apellido,
                    'sede' => $alumno->sede?->nombre
                        ?? $cuota->sede?->nombre
                        ?? $cuota->bloque?->sede?->nombre
                        ?? '—',
                    'monto' => (float) $cuota->monto,
                    'cuota_nombre' => $cuota->nombre,
                    'dot_class' => $isVencida ? 'dot-danger' : '',
                    'mes_label' => $mesLabel,
                    '_sort_venc' => $fv ? $fv->timestamp : 0,
                    '_sort_created' => $cuota->created_at?->timestamp ?? 0,
                ]);
            }
        }

        return $filas
            ->sort(function (array $a, array $b) {
                if ($a['_sort_venc'] !== $b['_sort_venc']) {
                    return $b['_sort_venc'] <=> $a['_sort_venc'];
                }

                return $b['_sort_created'] <=> $a['_sort_created'];
            })
            ->take($limit)
            ->map(fn (array $row) => collect($row)->except(['_sort_venc', '_sort_created'])->all())
            ->values();
    }

    /**
     * @param  list<int>|null  $filtroSedes
     */
    private function contarObjetivoCuota(Cuota $c, ?array $filtroSedes, AmbitoSedeService $ambito): int
    {
        return $this->alumnosObjetivoCuota($c, $filtroSedes, $ambito)->unique('id')->count();
    }

    /**
     * Alumnos a los que aplica una cuota del mes (lista explícita en cuota_alumno o por alcance).
     *
     * @param  list<int>|null  $filtroSedes
     * @return \Illuminate\Support\Collection<int, Alumno>
     */
    private function alumnosObjetivoCuota(Cuota $cuota, ?array $filtroSedes = null, ?AmbitoSedeService $ambito = null): \Illuminate\Support\Collection
    {
        if ($cuota->relationLoaded('alumnos') && $cuota->alumnos->isNotEmpty()) {
            $alumnos = $cuota->alumnos
                ->where('activo', true)
                ->loadMissing('sede')
                ->unique('id')
                ->values();
            if ($filtroSedes !== null && $ambito) {
                $ids = $filtroSedes !== [] ? $filtroSedes : [0];
                $alumnos = $alumnos->filter(function (Alumno $a) use ($ids) {
                    if (in_array((int) $a->sede_id, $ids, true)) {
                        return true;
                    }
                    $a->loadMissing('bloques');

                    return $a->bloques->contains(fn ($b) => in_array((int) $b->sede_id, $ids, true));
                })->values();
            }

            return $alumnos;
        }

        $alcance = Schema::hasColumn('cuotas', 'alcance')
            ? $cuota->alcanceNormalizado()
            : Cuota::ALCANCE_BLOQUE;

        if ($alcance === Cuota::ALCANCE_GENERAL) {
            $q = Alumno::query()
                ->where('activo', true)
                ->where(function ($q) {
                    $q->whereHas('bloques')->orWhereNotNull('bloque_id');
                })
                ->with('sede')
                ->orderBy('nombre_apellido');
            if ($filtroSedes !== null && $ambito) {
                $ambito->aplicarAlumnos($q, $filtroSedes);
            }

            return $q->get();
        }

        if ($alcance === Cuota::ALCANCE_SEDE && $cuota->sede_id) {
            $sid = (int) $cuota->sede_id;
            if ($filtroSedes !== null && ! in_array($sid, $filtroSedes, true)) {
                return collect();
            }

            return Alumno::query()
                ->where('activo', true)
                ->where(function ($q) use ($sid) {
                    $q->whereHas('bloques', fn ($b) => $b->where('bloques.sede_id', $sid))
                        ->orWhere('sede_id', $sid);
                })
                ->with('sede')
                ->orderBy('nombre_apellido')
                ->get();
        }

        if (! $cuota->bloque_id) {
            return collect();
        }

        $bid = (int) $cuota->bloque_id;
        if ($filtroSedes !== null) {
            $bloqueSede = (int) ($cuota->bloque?->sede_id ?? 0);
            if ($bloqueSede && ! in_array($bloqueSede, $filtroSedes, true)) {
                return collect();
            }
        }

        return Alumno::query()
            ->where('activo', true)
            ->where(function ($q) use ($bid) {
                if (Schema::hasTable('alumno_bloque')) {
                    $q->whereHas('bloques', fn ($b) => $b->where('bloques.id', $bid))
                        ->orWhere('bloque_id', $bid);
                } else {
                    $q->where('bloque_id', $bid);
                }
            })
            ->with('sede')
            ->orderBy('nombre_apellido')
            ->get();
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
