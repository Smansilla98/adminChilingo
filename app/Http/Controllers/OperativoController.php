<?php

namespace App\Http\Controllers;

use App\Models\Asistencia;
use App\Models\Bloque;
use App\Models\ComprobanteCuotaAlumno;
use App\Models\Cuota;
use App\Models\PagoDetalle;
use App\Models\User;
use App\Services\AmbitoSedeService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class OperativoController extends Controller
{
    public function pendientes(AmbitoSedeService $ambito)
    {
        /** @var User $user */
        $user = auth()->user();
        $esAdmin = $user->isAdmin();
        $filtroSedes = $ambito->idsPara($user);
        $bloqueIds = [];

        if (! $esAdmin && $filtroSedes === null && $user->isProfesor()) {
            $bloqueIds = $user->profesor?->bloqueIdsDondeParticipa()->all() ?? [];
        }

        $comprobantes = collect();
        if (Schema::hasTable('comprobantes_cuota_alumnos')) {
            $q = ComprobanteCuotaAlumno::query()
                ->with(['alumno', 'sede', 'items.bloque'])
                ->where('estado', 'pendiente')
                ->latest()
                ->limit(20);
            if ($filtroSedes !== null) {
                $ambito->aplicarComprobantes($q, $filtroSedes);
            } elseif (! $esAdmin) {
                $q->whereHas('items', fn ($i) => $i->whereIn('bloque_id', $bloqueIds !== [] ? $bloqueIds : [0]));
            }
            $comprobantes = $q->get();
        }

        $asistenciasHoy = collect();
        $hoy = now()->toDateString();
        if (Schema::hasTable('bloques') && Schema::hasTable('asistencias')) {
            $bloquesQ = Bloque::query()->where('activo', true)->with('sede');
            if ($filtroSedes !== null) {
                $ambito->aplicarBloques($bloquesQ, $filtroSedes);
            } elseif (! $esAdmin) {
                $bloquesQ->whereIn('id', $bloqueIds !== [] ? $bloqueIds : [0]);
            }
            $bloques = $bloquesQ->orderBy('nombre')->get();
            foreach ($bloques as $b) {
                $diaIso = (int) now()->dayOfWeekIso;
                $tieneClaseHoy = $b->horarios()->where('dia_semana', $diaIso)->exists()
                    || $b->horarios()->count() === 0; // sin horarios: asumir posible
                if (! $tieneClaseHoy && $b->horarios()->count() > 0) {
                    continue;
                }
                $alumnos = $b->alumnos()->where('alumnos.activo', true)->count();
                if ($alumnos === 0) {
                    continue;
                }
                $marcadas = Asistencia::query()
                    ->where('bloque_id', $b->id)
                    ->whereDate('fecha', $hoy)
                    ->count();
                if ($marcadas < $alumnos) {
                    $asistenciasHoy->push([
                        'bloque' => $b,
                        'alumnos' => $alumnos,
                        'marcadas' => $marcadas,
                        'pendientes' => max(0, $alumnos - $marcadas),
                    ]);
                }
            }
        }

        $cuotasPendientes = collect();
        $puedeVerCuotas = $esAdmin || $filtroSedes !== null;
        if ($puedeVerCuotas && Schema::hasTable('cuotas') && Schema::hasTable('pago_detalles')) {
            $mes = (int) now()->month;
            $anio = (int) now()->year;
            $cuotasQ = Cuota::query()
                ->where('activo', true)
                ->where('mes', $mes)
                ->where('año', $anio)
                ->with(['bloque.sede', 'sede'])
                ->orderBy('nombre')
                ->limit(12);
            if ($filtroSedes !== null) {
                $ambito->aplicarCuotas($cuotasQ, $filtroSedes);
            }
            $cuotas = $cuotasQ->get();

            foreach ($cuotas as $c) {
                if ($filtroSedes !== null && ! $ambito->cuotaTocaSedes($c, $filtroSedes)) {
                    continue;
                }
                $pagados = PagoDetalle::query()->where('cuota_id', $c->id)->distinct('alumno_id')->count('alumno_id');
                $cuotasPendientes->push([
                    'cuota' => $c,
                    'pagados' => $pagados,
                ]);
            }
        }

        return view('operativo.pendientes', [
            'comprobantes' => $comprobantes,
            'asistenciasHoy' => $asistenciasHoy,
            'cuotasPendientes' => $cuotasPendientes,
            'esAdmin' => $esAdmin || $filtroSedes !== null,
        ]);
    }

    public function cierreMes()
    {
        /** @var User $user */
        $user = auth()->user();
        if (! $user->isAdmin()) {
            abort(403);
        }

        $mes = (int) request('mes', now()->month);
        $anio = (int) request('anio', now()->year);
        $mes = max(1, min(12, $mes));

        $checklist = [];

        // 1) Asistencias
        $bloques = Schema::hasTable('bloques')
            ? Bloque::query()->where('activo', true)->with('sede')->orderBy('nombre')->get()
            : collect();
        $bloquesSinAsist = 0;
        foreach ($bloques as $b) {
            $count = Schema::hasTable('asistencias')
                ? Asistencia::query()
                    ->where('bloque_id', $b->id)
                    ->whereMonth('fecha', $mes)
                    ->whereYear('fecha', $anio)
                    ->count()
                : 0;
            if ($count === 0) {
                $bloquesSinAsist++;
            }
        }
        $checklist[] = [
            'clave' => 'asistencias',
            'titulo' => 'Asistencias del mes',
            'ok' => $bloquesSinAsist === 0,
            'detalle' => $bloquesSinAsist === 0
                ? 'Todos los bloques activos tienen al menos un registro.'
                : "{$bloquesSinAsist} bloque(s) sin ninguna asistencia cargada.",
            'href' => route('asistencias.index', ['mes' => $mes, 'año' => $anio]),
            'accion' => 'Ir a asistencias',
        ];

        // 2) Comprobantes pendientes
        $compPend = Schema::hasTable('comprobantes_cuota_alumnos')
            ? ComprobanteCuotaAlumno::query()->where('estado', 'pendiente')->count()
            : 0;
        $checklist[] = [
            'clave' => 'comprobantes',
            'titulo' => 'Comprobantes por revisar',
            'ok' => $compPend === 0,
            'detalle' => $compPend === 0 ? 'No hay comprobantes pendientes.' : "{$compPend} pendiente(s).",
            'href' => route('comprobantes-cuota-alumnos.index', ['estado' => 'pendiente']),
            'accion' => 'Revisar comprobantes',
        ];

        // 3) Cuotas del mes
        $cuotasMes = Schema::hasTable('cuotas')
            ? Cuota::query()->where('activo', true)->where('mes', $mes)->where('año', $anio)->count()
            : 0;
        $checklist[] = [
            'clave' => 'cuotas',
            'titulo' => 'Cuotas emitidas del mes',
            'ok' => $cuotasMes > 0,
            'detalle' => $cuotasMes > 0 ? "{$cuotasMes} cuota(s) activas." : 'No hay cuotas cargadas para este mes.',
            'href' => route('cuotas.index'),
            'accion' => 'Ver cuotas',
        ];

        // 4) Facturación (si existe ruta)
        $factHref = null;
        try {
            $factHref = route('facturacion-mensual.index');
        } catch (\Throwable $e) {
        }
        $checklist[] = [
            'clave' => 'facturacion',
            'titulo' => 'Facturación mensual',
            'ok' => null,
            'detalle' => 'Revisá que el mes esté generado y coherente con los cobros.',
            'href' => $factHref,
            'accion' => $factHref ? 'Abrir facturación' : null,
        ];

        $mesLabel = Carbon::createFromDate($anio, $mes, 1)->locale('es')->translatedFormat('F Y');

        return view('operativo.cierre-mes', [
            'checklist' => $checklist,
            'mes' => $mes,
            'anio' => $anio,
            'mesLabel' => $mesLabel,
            'okCount' => collect($checklist)->where('ok', true)->count(),
            'totalCheck' => collect($checklist)->whereNotNull('ok')->count(),
        ]);
    }
}
