<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Alumno;
use App\Models\Profesor;
use App\Models\Bloque;
use App\Models\BloqueHorario;
use App\Models\Cuota;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\Asistencia;
use App\Models\Sede;
use App\Models\Evento;
use App\Models\ComprobanteCuotaAlumno;
use App\Models\Gasto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        // Si es profesor, mostrar dashboard limitado
        if ($user->isProfesor() && !$user->isAdmin()) {
            return $this->dashboardProfesor();
        }

        // Dashboard Admin (protegido si faltan tablas por migraciones pendientes)
        try {
            $alumnosActivos = Alumno::where('activo', true)->count();
            $bloquesActivos = Bloque::where('activo', true)->count();
            $alumnosNuevosMes = Alumno::query()
                ->where('activo', true)
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->count();
            $sedesActivasEnBloques = (int) Bloque::query()
                ->where('activo', true)
                ->distinct()
                ->count('sede_id');

            $mesActual = (int) now()->month;
            $anioActual = (int) now()->year;

            // Total "cuotas emitidas" del mes = suma de asignaciones (cuota_alumno o todos los alumnos del bloque)
            $cuotasMes = Cuota::query()
                ->with([
                    'alumnos' => fn ($q) => $q->where('activo', true)->with('sede'),
                    'bloque.alumnos' => fn ($q) => $q->where('alumnos.activo', true),
                    'bloque.sede',
                    'sede',
                ])
                ->where('año', $anioActual)
                ->where('mes', $mesActual)
                ->where('activo', true)
                ->get();

            $cuotasTotal = 0;
            foreach ($cuotasMes as $c) {
                $alc = Schema::hasColumn('cuotas', 'alcance') ? $c->alcanceNormalizado() : Cuota::ALCANCE_BLOQUE;
                if ($alc === Cuota::ALCANCE_GENERAL) {
                    $objetivo = $c->alumnos->isNotEmpty()
                        ? $c->alumnos->unique('id')->count()
                        : (int) Alumno::query()->where('activo', true)->where(function ($q) {
                            $q->whereHas('bloques')->orWhereNotNull('bloque_id');
                        })->count();
                } elseif ($alc === Cuota::ALCANCE_SEDE && $c->sede_id) {
                    $sid = (int) $c->sede_id;
                    $objetivo = $c->alumnos->isNotEmpty()
                        ? $c->alumnos->unique('id')->count()
                        : (int) Alumno::query()->where('activo', true)->where(function ($q) use ($sid) {
                            $q->whereHas('bloques', fn ($b) => $b->where('bloques.sede_id', $sid))
                                ->orWhere('sede_id', $sid);
                        })->count();
                } else {
                    $objetivo = $c->alumnos->isNotEmpty()
                        ? $c->alumnos->unique('id')->count()
                        : ($c->bloque?->alumnos?->unique('id')->count() ?? 0);
                }
                $cuotasTotal += $objetivo;
            }

            $cuotasAbonadas = (int) DB::table('pago_detalles')
                ->join('pagos', 'pago_detalles.pago_id', '=', 'pagos.id')
                ->join('cuotas', 'pago_detalles.cuota_id', '=', 'cuotas.id')
                ->whereYear('pagos.fecha_pago', $anioActual)
                ->whereMonth('pagos.fecha_pago', $mesActual)
                ->where('cuotas.año', $anioActual)
                ->where('cuotas.mes', $mesActual)
                ->distinct()
                ->count(DB::raw("concat(pago_detalles.alumno_id,'-',pago_detalles.cuota_id)"));

            $cuotasPendientes = max(0, $cuotasTotal - $cuotasAbonadas);
            $pctAbonadas = $cuotasTotal > 0 ? round(($cuotasAbonadas / $cuotasTotal) * 100) : 0;
            $pctPendientes = $cuotasTotal > 0 ? round(($cuotasPendientes / $cuotasTotal) * 100) : 0;

            // Profesores (top 8) por cantidad de alumnos activos en sus bloques
            $profBase = Profesor::query()
                ->where('activo', true)
                ->with(['bloques' => function ($q) {
                    $q->where('activo', true)->with(['sede', 'alumnos' => fn ($qa) => $qa->where('alumnos.activo', true)]);
                }])
                ->get()
                ->map(function (Profesor $p) {
                    $alumnosIds = $p->bloques->flatMap(fn ($b) => $b->alumnos->pluck('id'))->unique();
                    $p->alumnos_count = $alumnosIds->count();
                    $p->bloques_count = $p->bloques->count();
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
                ->with(['bloque' => function ($q) {
                    $q->where('activo', true)->with(['sede', 'profesor', 'alumnos' => fn ($qa) => $qa->where('alumnos.activo', true)]);
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
                ->whereIn('bloque_id', $bloqueIds->all())
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

                $totalAlumnos = $bloque ? (int) $bloque->alumnos()->where('activo', true)->count() : 0;

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
            $cuotasPendientesList = $this->armarListadoCobrosPendientes($cuotasMes, $hoy, 8);

            // Recaudación últimas 6 semanas (sum monto_total por fecha_pago)
            $recaudacion = collect(range(5, 0))->map(function ($i) {
                $inicio = Carbon::now()->startOfWeek(Carbon::MONDAY)->subWeeks($i);
                $fin = $inicio->copy()->endOfWeek(Carbon::SUNDAY);
                return (float) Pago::whereBetween('fecha_pago', [$inicio->toDateString(), $fin->toDateString()])->sum('monto_total');
            });

            $cobradoMes = (float) Pago::query()
                ->whereYear('fecha_pago', $anioActual)
                ->whereMonth('fecha_pago', $mesActual)
                ->sum('monto_total');

            $proximosEventos = Evento::query()->proximos()->orderBy('fecha')->limit(5)->get();
            $proximosEventosCount = (int) Evento::query()->proximos()->count();

            $comprobantesPendientesCount = 0;
            $comprobantesPendientesList = collect();
            if (Schema::hasTable('comprobantes_cuota_alumnos')) {
                $comprobantesPendientesCount = (int) ComprobanteCuotaAlumno::query()
                    ->where('estado', 'pendiente')
                    ->count();
                $comprobantesPendientesList = ComprobanteCuotaAlumno::query()
                    ->where('estado', 'pendiente')
                    ->with('alumno')
                    ->latest()
                    ->limit(5)
                    ->get();
            }

            $bloquesCupo = Bloque::query()
                ->where('activo', true)
                ->with(['sede', 'profesor'])
                ->withCount(['alumnos as alumnos_activos_count' => fn ($q) => $q->where('alumnos.activo', true)])
                ->orderBy('nombre')
                ->take(12)
                ->get();

            $chartLabels = [];
            $chartIngresos = [];
            $chartGastos = [];
            for ($i = 5; $i >= 0; $i--) {
                $d = now()->subMonths($i);
                $chartLabels[] = $d->locale('es')->translatedFormat('M y');
                $chartIngresos[] = (float) Pago::query()
                    ->whereYear('fecha_pago', $d->year)
                    ->whereMonth('fecha_pago', $d->month)
                    ->sum('monto_total');
                $chartGastos[] = Schema::hasTable('gastos')
                    ? (float) Gasto::query()->whereYear('fecha', $d->year)->whereMonth('fecha', $d->month)->sum('monto')
                    : 0.0;
            }

            $alumnosPorSedeChart = Sede::query()
                ->orderBy('nombre')
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

            $asistenciasMes = (int) Asistencia::query()
                ->whereYear('fecha', $anioActual)
                ->whereMonth('fecha', $mesActual)
                ->count();

            $adminNombre = trim(auth()->user()->name ?: auth()->user()->username ?: '');

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
                'adminNombre'
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
                $bloques = $profesor->bloquesActivos()->with('sede', 'alumnos')->get();
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

        return view('dashboard.profesor', compact('bloques', 'proximosEventos', 'comprobantesCuotaPendientes'));
    }

    /**
     * Listado lateral de cobros pendientes (alumno + cuota del mes sin línea en pago_detalles).
     *
     * @param  \Illuminate\Support\Collection<int, Cuota>  $cuotasMes
     */
    private function armarListadoCobrosPendientes($cuotasMes, Carbon $hoy, int $limit = 8): \Illuminate\Support\Collection
    {
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
            foreach ($this->alumnosObjetivoCuota($cuota) as $alumno) {
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
     * Alumnos a los que aplica una cuota del mes (lista explícita en cuota_alumno o por alcance).
     *
     * @return \Illuminate\Support\Collection<int, Alumno>
     */
    private function alumnosObjetivoCuota(Cuota $cuota): \Illuminate\Support\Collection
    {
        if ($cuota->relationLoaded('alumnos') && $cuota->alumnos->isNotEmpty()) {
            return $cuota->alumnos
                ->where('activo', true)
                ->loadMissing('sede')
                ->unique('id')
                ->values();
        }

        $alcance = Schema::hasColumn('cuotas', 'alcance')
            ? $cuota->alcanceNormalizado()
            : Cuota::ALCANCE_BLOQUE;

        if ($alcance === Cuota::ALCANCE_GENERAL) {
            return Alumno::query()
                ->where('activo', true)
                ->where(function ($q) {
                    $q->whereHas('bloques')->orWhereNotNull('bloque_id');
                })
                ->with('sede')
                ->orderBy('nombre_apellido')
                ->get();
        }

        if ($alcance === Cuota::ALCANCE_SEDE && $cuota->sede_id) {
            $sid = (int) $cuota->sede_id;

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
