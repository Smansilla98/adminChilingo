<?php

namespace App\Services;

use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\BloqueHorario;
use App\Models\Evento;
use App\Models\ObservacionPedagogica;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class EspacioAlumnoService
{
    /**
     * @return array{proximaClase: ?array, asistencias: Collection, proximoPaso: ?ObservacionPedagogica, eventos: Collection}
     */
    public function armar(?Alumno $alumno): array
    {
        $vacio = [
            'proximaClase' => null,
            'asistencias' => collect(),
            'proximoPaso' => null,
            'eventos' => collect(),
        ];
        if (! $alumno) {
            return $vacio;
        }

        $alumno->loadMissing(['bloques.horarios', 'bloques.sede', 'bloque.horarios', 'bloque.sede', 'sede']);
        $diaIso = (int) now()->isoWeekday();
        $proximaClase = null;
        foreach ($alumno->bloques->isNotEmpty() ? $alumno->bloques : collect([$alumno->bloque])->filter() as $bloque) {
            $h = $bloque->horarios?->firstWhere('dia_semana', $diaIso)
                ?? $bloque->horarios?->sortBy('dia_semana')->first();
            if ($h && $proximaClase === null) {
                $proximaClase = [
                    'bloque' => $bloque->nombre,
                    'sede' => $bloque->sede->nombre ?? $alumno->sede->nombre ?? null,
                    'dia' => BloqueHorario::DIAS_SEMANA[$h->dia_semana] ?? '',
                    'hora' => $h->hora_inicio ? substr((string) $h->hora_inicio, 0, 5) : null,
                    'es_hoy' => (int) $h->dia_semana === $diaIso,
                ];
            }
            if ($h && (int) $h->dia_semana === $diaIso) {
                $proximaClase = [
                    'bloque' => $bloque->nombre,
                    'sede' => $bloque->sede->nombre ?? $alumno->sede->nombre ?? null,
                    'dia' => BloqueHorario::DIAS_SEMANA[$h->dia_semana] ?? '',
                    'hora' => $h->hora_inicio ? substr((string) $h->hora_inicio, 0, 5) : null,
                    'es_hoy' => true,
                ];
                break;
            }
        }

        $asistencias = Asistencia::query()
            ->where('alumno_id', $alumno->id)
            ->orderByDesc('fecha')
            ->limit(6)
            ->get();

        $proximoPaso = null;
        if (Schema::hasTable('observaciones_pedagogicas')) {
            $q = ObservacionPedagogica::query()
                ->where('alumno_id', $alumno->id)
                ->orderByDesc('fecha')
                ->orderByDesc('id');
            if (Schema::hasColumn('observaciones_pedagogicas', 'visible_alumno')) {
                $q->where('visible_alumno', true);
            }
            $proximoPaso = $q->first();
        }

        $eventos = Evento::query()->proximos()->orderBy('fecha')->limit(4)->get();

        return compact('proximaClase', 'asistencias', 'proximoPaso', 'eventos');
    }
}
