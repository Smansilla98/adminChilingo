<?php

namespace App\Services\Dashboard;

use App\Models\Alumno;
use App\Models\Bloque;
use App\Models\ComprobanteCuotaAlumno;
use App\Models\Evento;
use App\Models\Sede;
use App\Services\AmbitoSedeService;
use Illuminate\Support\Facades\Schema;

/**
 * Próximos eventos, comprobantes por revisar, bloques con cupo y alumnos por sede del panel de administración.
 */
class ActividadPanelService
{
    /**
     * @return array<string, mixed>
     */
    public function armar(?array $filtroSedes, AmbitoSedeService $ambito): array
    {
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

        return compact(
            'proximosEventos',
            'proximosEventosCount',
            'comprobantesPendientesCount',
            'comprobantesPendientesList',
            'bloquesCupo',
            'alumnosPorSedeChart'
        );
    }
}
