<?php

namespace App\Services\Dashboard;

use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\Bloque;
use App\Models\User;
use App\Services\AmbitoSedeService;
use Illuminate\Support\Facades\Cache;

/**
 * KPIs del panel de administración (alumnos, bloques, asistencias del mes). Cacheado 90 s por usuario y alcance.
 */
class DashboardKpiService
{
    /**
     * @return array<string, mixed>
     */
    public function armar(User $user, ?array $filtroSedes, AmbitoSedeService $ambito, int $anioActual, int $mesActual): array
    {
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

        return compact(
            'alumnosActivos',
            'bloquesActivos',
            'alumnosNuevosMes',
            'sedesActivasEnBloques',
            'asistenciasMes'
        );
    }
}
