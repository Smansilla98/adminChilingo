<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\Bloque;
use App\Models\BloqueHorario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * «Mi asistencia» del alumno en la app: presentes, ausentes y porcentaje por bloque y por
 * mes del año, las últimas clases y su horario semanal fijo. Solo ve lo propio (las
 * fichas de alumno vinculadas a su persona o a su usuario), igual que mi/estado-cuenta.
 */
class MiAsistenciaController extends Controller
{
    /** Tipos que no cuentan como clase dada (no suman ni restan al porcentaje). */
    private const NO_CUENTAN = ['feriado', 'sin_clases'];

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $anio = $request->integer('anio') ?: (int) now()->year;
        abort_unless($anio >= 2000 && $anio <= 2100, 422);

        $alumnos = Alumno::query()
            ->where(fn ($q) => $q->where('persona_id', $user->persona_id ?: 0)->orWhere('user_id', $user->id))
            ->with(['bloques' => fn ($q) => $q->with(['sede:id,nombre', 'horarios'])])
            ->get();

        return response()->json([
            'anio' => $anio,
            'alumnos' => $alumnos->map(fn (Alumno $a) => $this->deAlumno($a, $anio))->values(),
        ]);
    }

    /** @return array<string, mixed> */
    private function deAlumno(Alumno $alumno, int $anio): array
    {
        $registros = Asistencia::query()
            ->where('alumno_id', $alumno->id)
            ->whereYear('fecha', $anio)
            ->orderByDesc('fecha')
            ->get(['id', 'bloque_id', 'fecha', 'tipo_asistencia']);

        // Bloques actuales del alumno + los que tienen registros del año (aunque ya no esté).
        $ids = $alumno->bloques->pluck('id')->merge($registros->pluck('bloque_id'))->unique()->filter()->values();
        $faltantes = $ids->diff($alumno->bloques->pluck('id'));
        $bloques = $alumno->bloques->concat(
            $faltantes->isEmpty() ? [] : Bloque::query()->with(['sede:id,nombre', 'horarios'])->whereIn('id', $faltantes)->get()
        );

        return [
            'alumno_id' => $alumno->id,
            'nombre' => $alumno->nombre_apellido,
            'resumen' => $this->resumen($registros),
            'bloques' => $bloques->map(fn (Bloque $b) => [
                'bloque_id' => $b->id,
                'nombre' => $b->nombre,
                'sede' => $b->sede?->nombre,
                'actual' => $alumno->bloques->contains('id', $b->id),
                'horarios' => $b->horarios->sortBy(['dia_semana', 'hora_inicio'])->map(fn (BloqueHorario $h) => [
                    'dia' => $h->dia_semana,
                    'dia_nombre' => $h->nombre_dia,
                    'inicio' => $h->hora_inicio->format('H:i'),
                    'fin' => $h->hora_fin->format('H:i'),
                ])->values(),
                'resumen' => $this->resumen($registros->where('bloque_id', $b->id)),
                'meses' => $registros->where('bloque_id', $b->id)
                    ->groupBy(fn (Asistencia $r) => (int) $r->fecha->format('n'))
                    ->sortKeysDesc()
                    ->map(fn (Collection $delMes, int $mes) => ['mes' => $mes] + $this->resumen($delMes))
                    ->values(),
            ])->values(),
            'ultimas' => $registros->take(10)->map(fn (Asistencia $r) => [
                'fecha' => $r->fecha->toDateString(),
                'bloque' => $bloques->firstWhere('id', $r->bloque_id)?->nombre,
                'tipo' => $r->tipo_asistencia,
                'tipo_etiqueta' => Asistencia::TIPOS_ASISTENCIA[$r->tipo_asistencia] ?? $r->tipo_asistencia,
            ])->values(),
        ];
    }

    /**
     * @param  Collection<int, Asistencia>  $registros
     * @return array{clases: int, presentes: int, tardes: int, ausentes: int, justificadas: int, porcentaje: int|null}
     */
    private function resumen(Collection $registros): array
    {
        $cuenta = fn (array $tipos) => $registros->whereIn('tipo_asistencia', $tipos)->count();
        $presentes = $cuenta(['presente']);
        $tardes = $cuenta(['tarde']);
        $ausentes = $cuenta(['ausencia_injustificada', 'ausente']);
        $justificadas = $cuenta(['ausencia_justificada', 'justificado']);
        $clases = $registros->whereNotIn('tipo_asistencia', self::NO_CUENTAN)->count();

        return [
            'clases' => $clases,
            'presentes' => $presentes,
            'tardes' => $tardes,
            'ausentes' => $ausentes,
            'justificadas' => $justificadas,
            // Presentes + tardes sobre las clases dadas (sin feriados ni días sin clase).
            'porcentaje' => $clases > 0 ? (int) round(($presentes + $tardes) * 100 / $clases) : null,
        ];
    }
}
