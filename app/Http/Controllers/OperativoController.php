<?php

namespace App\Http\Controllers;

use App\Domain\Operativo\CierreMesService;
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
        $acceso = $user->acceso();
        // Cuotas pendientes: alcance de cuotas.view (null = toda la escuela).
        $filtroSedes = $ambito->idsPara($user, 'cuotas.view');
        $esAdmin = $acceso->puedeGlobal('cuotas.view');

        $comprobantes = collect();
        if (Schema::hasTable('comprobantes_cuota_alumnos')) {
            $q = ComprobanteCuotaAlumno::query()
                ->with(['alumno', 'sede', 'items.bloque'])
                ->where('estado', 'pendiente')
                ->latest()
                ->limit(20);
            $alcance = $acceso->alcance('comprobantes.view');
            if (! $alcance->esGlobal()) {
                $q->where(fn ($w) => $w->whereIn('sede_id', $alcance->sedeIds() ?: [0])
                    ->orWhereHas('items', fn ($i) => $i->whereIn('bloque_id', $alcance->bloqueIds() ?: [0]))
                    ->orWhereHas('items.bloque', fn ($b) => $b->whereIn('sede_id', $alcance->sedeIds() ?: [0])));
            }
            $comprobantes = $q->get();
        }

        $asistenciasHoy = collect();
        $hoy = now()->toDateString();
        if (Schema::hasTable('bloques') && Schema::hasTable('asistencias')) {
            $bloquesQ = Bloque::query()->where('activo', true)->with('sede');
            $acceso->alcance('asistencias.create')->aplicarBloques($bloquesQ);
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
        $puedeVerCuotas = $acceso->puede('cuotas.view');
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

    public function cierreMes(CierreMesService $cierre)
    {
        /** @var User $user */
        $user = auth()->user();
        // El cierre de mes es de toda la escuela.
        if (! $user->acceso()->puedeGlobal('facturacion.view')) {
            abort(403);
        }

        $mes = max(1, min(12, (int) request('mes', now()->month)));
        $anio = (int) request('anio', now()->year);

        $enlaces = [
            'asistencias' => [route('asistencias.index', ['mes' => $mes, 'año' => $anio]), 'Ir a asistencias'],
            'comprobantes' => [route('comprobantes-cuota-alumnos.index', ['estado' => 'pendiente']), 'Revisar comprobantes'],
            'cuotas' => [route('cuotas.index'), 'Ver cuotas'],
            'facturacion' => [route('facturacion-mensual.index'), 'Abrir facturación'],
        ];
        $checklist = array_map(fn ($item) => $item + ['href' => $enlaces[$item['clave']][0] ?? null, 'accion' => $enlaces[$item['clave']][1] ?? null], $cierre->checklist($mes, $anio));

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
