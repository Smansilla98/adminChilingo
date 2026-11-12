<?php

namespace App\Services\Dashboard;

use App\Domain\Asistencias\AusenciasSeguidas;
use App\Models\ComprobanteCuotaAlumno;
use App\Models\Evento;
use App\Models\User;
use App\Services\JornadaDocenteService;
use Illuminate\Support\Facades\Schema;

/**
 * Panel del docente: sus bloques de hoy, asistencias pendientes y alumnos a contactar.
 */
class DashboardProfesorService
{
    /**
     * @return array<string, mixed>
     */
    public function armar(User $user): array
    {
        $bloques = collect();
        $proximosEventos = collect();
        $comprobantesCuotaPendientes = 0;
        $paraContactar = collect();
        try {
            $profesor = $user->profesor;

            if ($profesor) {
                $bloques = $profesor->bloquesActivos()->with(['sede', 'alumnos', 'horarios'])->get();
                $proximosEventos = Evento::where('profesor_id', $profesor->id)
                    ->proximos()
                    ->limit(5)
                    ->get();
                $paraContactar = app(AusenciasSeguidas::class)->enBloques($profesor->bloqueIdsDondeParticipa());
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

        return compact(
            'bloques',
            'bloquesHoy',
            'pendientesAsistencia',
            'proximosPasos',
            'proximosEventos',
            'comprobantesCuotaPendientes',
            'paraContactar'
        );
    }
}
