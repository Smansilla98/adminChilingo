<?php

namespace App\Services;

use App\Models\Asistencia;
use App\Models\ObservacionPedagogica;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class JornadaDocenteService
{
    /**
     * @return array{bloquesHoy: Collection, pendientesAsistencia: Collection, proximosPasos: Collection}
     */
    public function armar(User $user, Collection $bloques): array
    {
        $diaIso = (int) now()->isoWeekday();
        $hoy = now()->toDateString();
        $bloquesHoy = $bloques->filter(function ($b) use ($diaIso) {
            return $b->horarios && $b->horarios->contains(fn ($h) => (int) $h->dia_semana === $diaIso);
        })->values();

        $conAsistencia = [];
        if ($bloquesHoy->isNotEmpty() && Schema::hasTable('asistencias')) {
            $conAsistencia = Asistencia::query()
                ->whereDate('fecha', $hoy)
                ->whereIn('bloque_id', $bloquesHoy->pluck('id')->all())
                ->distinct()
                ->pluck('bloque_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }
        $pendientesAsistencia = $bloquesHoy->filter(
            fn ($b) => ! in_array((int) $b->id, $conAsistencia, true)
        )->values();

        $proximosPasos = collect();
        if (Schema::hasTable('observaciones_pedagogicas')) {
            $alumnoIds = $bloques->flatMap(fn ($b) => $b->alumnos?->pluck('id') ?? collect())->unique()->values();
            if ($alumnoIds->isNotEmpty()) {
                $q = ObservacionPedagogica::query()
                    ->with('alumno')
                    ->whereIn('alumno_id', $alumnoIds->all())
                    ->orderByDesc('fecha')
                    ->orderByDesc('id')
                    ->limit(12);
                if (Schema::hasColumn('observaciones_pedagogicas', 'proximo_paso')) {
                    $q->whereNotNull('proximo_paso')->where('proximo_paso', '!=', '');
                }
                $proximosPasos = $q->get()->unique('alumno_id')->take(8)->values();
            }
        }

        return compact('bloquesHoy', 'pendientesAsistencia', 'proximosPasos');
    }
}
