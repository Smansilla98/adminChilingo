<?php

namespace App\Services\Dashboard;

use App\Models\Asistencia;
use App\Models\BloqueHorario;
use App\Services\AmbitoSedeService;
use Carbon\Carbon;

/**
 * Cards de clases de la semana por bloque: tomada, incompleta o pendiente, con el porcentaje de presentes.
 */
class AsistenciaSemanaService
{
    /**
     * @return array<string, mixed>
     */
    public function armar(?array $filtroSedes, AmbitoSedeService $ambito, Carbon $hoy): array
    {
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

        return compact(
            'bloquesSemanales'
        );
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
