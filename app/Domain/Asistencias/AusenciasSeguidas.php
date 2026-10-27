<?php

namespace App\Domain\Asistencias;

use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\Bloque;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Alumnos que faltaron a las últimas clases seguidas de un bloque, sin aviso.
 *
 * Cuenta solo ausencias injustificadas; feriados, días sin clase y ausencias
 * justificadas no cortan la racha ni suman. Una presencia (o llegada tarde) la corta.
 */
class AusenciasSeguidas
{
    public const MINIMO = 3;

    private const VENTANA_DIAS = 120;

    /**
     * @param  iterable<int>  $bloqueIds
     * @return Collection<int, array{alumno: Alumno, bloque: Bloque, faltas: int, desde: Carbon, ultima_presencia: ?Carbon}>
     */
    public function enBloques(iterable $bloqueIds, int $minimo = self::MINIMO): Collection
    {
        $ids = collect($bloqueIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        $registros = Asistencia::query()
            ->whereIn('bloque_id', $ids)
            ->where('fecha', '>=', now()->subDays(self::VENTANA_DIAS)->toDateString())
            ->whereHas('alumno', fn ($q) => $q->where('activo', true))
            ->orderBy('alumno_id')->orderBy('bloque_id')->orderByDesc('fecha')
            ->get(['alumno_id', 'bloque_id', 'fecha', 'presente', 'tipo_asistencia']);

        $rachas = collect();
        foreach ($registros->groupBy(fn ($a) => $a->alumno_id.'-'.$a->bloque_id) as $grupo) {
            $faltas = 0;
            $desde = null;
            $ultimaPresencia = null;
            foreach ($grupo as $a) {
                $tipo = $this->tipo($a);
                if (in_array($tipo, ['feriado', 'sin_clases', 'justificada'], true)) {
                    continue;
                }
                if ($tipo === 'presente') {
                    $ultimaPresencia = $a->fecha;
                    break;
                }
                $faltas++;
                $desde = $a->fecha; // las fechas vienen de la más nueva a la más vieja
            }
            if ($faltas >= $minimo) {
                $rachas->push([
                    'alumno_id' => $grupo->first()->alumno_id,
                    'bloque_id' => $grupo->first()->bloque_id,
                    'faltas' => $faltas,
                    'desde' => $desde,
                    'ultima_presencia' => $ultimaPresencia,
                ]);
            }
        }

        if ($rachas->isEmpty()) {
            return collect();
        }

        $alumnos = Alumno::query()->whereIn('id', $rachas->pluck('alumno_id'))->get()->keyBy('id');
        $bloques = Bloque::query()->whereIn('id', $rachas->pluck('bloque_id'))->with('sede:id,nombre')->get()->keyBy('id');

        $resultado = collect();
        foreach ($rachas as $r) {
            $alumno = $alumnos->get($r['alumno_id']);
            $bloque = $bloques->get($r['bloque_id']);
            if ($alumno instanceof Alumno && $bloque instanceof Bloque && $r['desde'] instanceof Carbon) {
                $resultado->push([
                    'alumno' => $alumno,
                    'bloque' => $bloque,
                    'faltas' => (int) $r['faltas'],
                    'desde' => $r['desde'],
                    'ultima_presencia' => $r['ultima_presencia'] instanceof Carbon ? $r['ultima_presencia'] : null,
                ]);
            }
        }

        return $resultado->sortByDesc('faltas')->values();
    }

    /** presente | ausente | justificada | feriado | sin_clases */
    private function tipo(Asistencia $a): string
    {
        return match ($a->tipo_asistencia) {
            'presente', 'tarde' => 'presente',
            'ausencia_justificada', 'justificado' => 'justificada',
            'feriado' => 'feriado',
            'sin_clases' => 'sin_clases',
            'ausencia_injustificada', 'ausente' => 'ausente',
            default => $a->presente ? 'presente' : 'ausente', // registros viejos sin tipo
        };
    }
}
