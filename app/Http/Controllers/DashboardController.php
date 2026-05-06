<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Alumno;
use App\Models\Profesor;
use App\Models\Bloque;
use App\Models\BloqueHorario;
use App\Models\Cuota;
use App\Models\Pago;
use App\Models\Asistencia;
use App\Models\Sede;
use App\Models\Evento;
use Illuminate\Support\Facades\DB;
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
                ->with(['alumnos:id', 'bloque.alumnos' => fn ($q) => $q->where('alumnos.activo', true)])
                ->where('año', $anioActual)
                ->where('mes', $mesActual)
                ->where('activo', true)
                ->get();

            $cuotasTotal = 0;
            foreach ($cuotasMes as $c) {
                $objetivo = $c->alumnos->isNotEmpty()
                    ? $c->alumnos->unique('id')->count()
                    : ($c->bloque?->alumnos?->unique('id')->count() ?? 0);
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

            $asistAgg = Asistencia::query()
                ->selectRaw('bloque_id, fecha, COUNT(*) as total_reg, SUM(CASE WHEN presente = 1 THEN 1 ELSE 0 END) as presentes')
                ->whereBetween('fecha', [$inicioSemana->toDateString(), $finSemana->toDateString()])
                ->whereIn('bloque_id', $bloqueIds->all())
                ->groupBy('bloque_id', 'fecha')
                ->get()
                ->groupBy('bloque_id')
                ->map(fn ($rows) => $rows->sortByDesc('fecha')->first());

            $bloquesSemanales = $horarios->map(function (BloqueHorario $h) use ($inicioSemana, $asistAgg) {
                $bloque = $h->bloque;
                $fechaClase = $inicioSemana->copy()->addDays(max(0, ((int) $h->dia_semana) - 1));

                $totalAlumnos = $bloque?->alumnos?->unique('id')->count() ?? 0;
                $agg = $asistAgg->get($bloque->id);
                $regTotal = (int) ($agg->total_reg ?? 0);
                $presentes = (int) ($agg->presentes ?? 0);

                if ($regTotal > 0 && $regTotal >= $totalAlumnos && $totalAlumnos > 0) {
                    $estado = 'Tomada';
                    $badge = 'badge-ok';
                } elseif ($regTotal > 0 && $totalAlumnos > 0 && $regTotal < $totalAlumnos) {
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

            // Cuotas pendientes del mes (últimas 5 asignaciones sin pago)
            $cuotasPendientesList = collect(DB::table('cuotas as c')
                ->join('bloques as b', 'c.bloque_id', '=', 'b.id')
                ->join('alumno_bloque as ab', 'ab.bloque_id', '=', 'b.id')
                ->join('alumnos as a', 'a.id', '=', 'ab.alumno_id')
                ->join('sedes as s', 's.id', '=', 'a.sede_id')
                ->leftJoin('pago_detalles as pd', function ($join) {
                    $join->on('pd.cuota_id', '=', 'c.id')->on('pd.alumno_id', '=', 'a.id');
                })
                ->whereNull('pd.id')
                ->where('c.año', $anioActual)
                ->where('c.mes', $mesActual)
                ->where('c.activo', true)
                ->where('a.activo', true)
                ->orderByDesc('c.fecha_vencimiento')
                ->orderByDesc('c.created_at')
                ->limit(5)
                ->get([
                    'a.nombre_apellido as alumno',
                    's.nombre as sede',
                    'c.monto as monto',
                    'c.fecha_vencimiento as fecha_vencimiento',
                    'c.created_at as created_at',
                ]));

            $hoy = Carbon::today();
            $cuotasPendientesList = $cuotasPendientesList->map(function ($row) use ($hoy) {
                $fv = $row->fecha_vencimiento ? Carbon::parse($row->fecha_vencimiento) : null;
                $isVencida = $fv ? $fv->lt($hoy->copy()->subDays(5)) : false;
                return [
                    'alumno' => $row->alumno,
                    'sede' => $row->sede,
                    'monto' => (float) $row->monto,
                    'dot_class' => $isVencida ? 'dot-danger' : '',
                    'mes_label' => now()->locale('es')->translatedFormat('M Y'),
                ];
            });

            // Recaudación últimas 6 semanas (sum monto_total por fecha_pago)
            $recaudacion = collect(range(5, 0))->map(function ($i) {
                $inicio = Carbon::now()->startOfWeek(Carbon::MONDAY)->subWeeks($i);
                $fin = $inicio->copy()->endOfWeek(Carbon::SUNDAY);
                return (float) Pago::whereBetween('fecha_pago', [$inicio->toDateString(), $fin->toDateString()])->sum('monto_total');
            });

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
                'recaudacion'
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
            ]);
        }
    }

    private function dashboardProfesor()
    {
        $user = auth()->user();
        $bloques = collect();
        $proximosEventos = collect();

        try {
            $profesor = $user->profesor;

            if ($profesor) {
                $bloques = $profesor->bloquesActivos()->with('sede', 'alumnos')->get();
                $proximosEventos = Evento::where('profesor_id', $profesor->id)
                    ->proximos()
                    ->limit(5)
                    ->get();
            }
        } catch (\Illuminate\Database\QueryException $e) {
            // Tabla profesors/profesores u otras no existen (migraciones pendientes)
        } catch (\Throwable $e) {
            // Cualquier otro fallo: mostrar dashboard vacío
        }

        return view('dashboard.profesor', compact('bloques', 'proximosEventos'));
    }
}
