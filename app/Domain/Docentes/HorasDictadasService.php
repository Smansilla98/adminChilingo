<?php

namespace App\Domain\Docentes;

use App\Models\AjusteHorasDocente;
use App\Models\Asistencia;
use App\Models\Bloque;
use App\Models\BloqueHorario;
use App\Models\Profesor;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Horas dictadas por un docente en un mes.
 *
 * Regla: una clase está dictada si ese día se tomó asistencia en el bloque (sin contar
 * días marcados como feriado o sin clases). La duración sale del horario del bloque para
 * ese día de la semana; si el bloque no tiene horario ese día se usa su primer horario,
 * y si no tiene ninguno, HORAS_SIN_HORARIO (queda marcado para revisar).
 * Cuenta para el titular y el equipo del bloque, salvo el rol «suplente»: las suplencias
 * se cargan como ajuste manual, igual que las clases extra y las ausencias.
 */
class HorasDictadasService
{
    public const HORAS_SIN_HORARIO = 2.0;

    /** @return Collection<int, Bloque> */
    public function bloquesQueDicta(Profesor $profesor): Collection
    {
        $desdePivot = DB::table('bloque_profesor')->where('profesor_id', $profesor->id)->where(fn ($q) => $q->whereNull('rol')->orWhere('rol', '!=', 'suplente'))->pluck('bloque_id');
        $ids = $desdePivot->merge(Bloque::query()->where('profesor_id', $profesor->id)->pluck('id'))->unique()->values()->all();

        return Bloque::query()->whereIn('id', $ids ?: [0])->with(['horarios', 'sede:id,nombre'])->get();
    }

    /**
     * @return array{
     *   clases: list<array{fecha: string, bloque_id: int, bloque: string, sede_id: ?int, horas: float, sin_horario: bool}>,
     *   ajustes: list<array{id: int, fecha: string, tipo: string, tipo_nombre: string, horas: float, motivo: ?string, sede_id: ?int, bloque: ?string}>,
     *   por_sede: array<int, array{sede_id: ?int, sede: string, clases: int, horas_dictadas: float, horas_ajuste: float}>,
     *   horas_dictadas: float,
     *   horas_ajuste: float
     * }
     */
    public function calcular(Profesor $profesor, int $anio, int $mes): array
    {
        $inicio = Carbon::create($anio, $mes, 1)->startOfMonth();
        $fin = $inicio->copy()->endOfMonth();
        $bloques = $this->bloquesQueDicta($profesor)->keyBy('id');

        $fechas = Asistencia::query()
            ->whereIn('bloque_id', $bloques->keys()->all() ?: [0])
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->where(fn ($q) => $q->whereNull('tipo_asistencia')->orWhereNotIn('tipo_asistencia', ['feriado', 'sin_clases']))
            ->toBase()->select('bloque_id', 'fecha')->distinct()->orderBy('fecha')->get();

        $clases = [];
        foreach ($fechas as $f) {
            $bloque = $bloques->get((int) $f->bloque_id);
            if (! $bloque) {
                continue;
            }
            $fecha = Carbon::parse($f->fecha);
            [$horas, $sinHorario] = $this->duracion($bloque, $fecha);
            $clases[] = [
                'fecha' => $fecha->toDateString(),
                'bloque_id' => $bloque->id,
                'bloque' => $bloque->nombre,
                'sede_id' => $bloque->sede_id ? (int) $bloque->sede_id : null,
                'horas' => $horas,
                'sin_horario' => $sinHorario,
            ];
        }
        // La misma fecha puede figurar dos veces si la base guarda hora en `fecha`.
        $clases = array_values(collect($clases)->unique(fn ($c) => $c['bloque_id'].'|'.$c['fecha'])->all());

        $ajustes = AjusteHorasDocente::query()->where('profesor_id', $profesor->id)
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->with('bloque:id,nombre,sede_id')->orderBy('fecha')->get()
            ->map(fn (AjusteHorasDocente $a) => [
                'id' => $a->id,
                'fecha' => $a->fecha->toDateString(),
                'tipo' => $a->tipo,
                'tipo_nombre' => AjusteHorasDocente::TIPOS[$a->tipo] ?? $a->tipo,
                'horas' => $a->horasConSigno(),
                'motivo' => $a->motivo,
                'sede_id' => $a->sede_id ? (int) $a->sede_id : ($a->bloque?->sede_id ? (int) $a->bloque->sede_id : null),
                'bloque' => $a->bloque?->nombre,
            ])->values()->all();

        $nombresSede = $bloques->mapWithKeys(fn (Bloque $b) => [(int) $b->sede_id => $b->sede->nombre ?? 'Sin sede']);
        $porSede = [];
        foreach ($clases as $c) {
            $k = (int) $c['sede_id'];
            $porSede[$k] ??= ['sede_id' => $c['sede_id'], 'sede' => $nombresSede[$k] ?? 'Sin sede', 'clases' => 0, 'horas_dictadas' => 0.0, 'horas_ajuste' => 0.0];
            $porSede[$k]['clases']++;
            $porSede[$k]['horas_dictadas'] += $c['horas'];
        }
        foreach ($ajustes as $a) {
            $k = (int) $a['sede_id'];
            $porSede[$k] ??= ['sede_id' => $a['sede_id'], 'sede' => $nombresSede[$k] ?? \App\Models\Sede::query()->whereKey($k)->value('nombre') ?? 'Sin sede', 'clases' => 0, 'horas_dictadas' => 0.0, 'horas_ajuste' => 0.0];
            $porSede[$k]['horas_ajuste'] += $a['horas'];
        }
        foreach ($porSede as &$p) {
            $p['horas_dictadas'] = round($p['horas_dictadas'], 2);
            $p['horas_ajuste'] = round($p['horas_ajuste'], 2);
        }
        unset($p);

        return [
            'clases' => $clases,
            'ajustes' => $ajustes,
            'por_sede' => $porSede,
            'horas_dictadas' => round(array_sum(array_column($clases, 'horas')), 2),
            'horas_ajuste' => round(array_sum(array_column($ajustes, 'horas')), 2),
        ];
    }

    /** @return array{0: float, 1: bool} horas y si faltó horario */
    private function duracion(Bloque $bloque, Carbon $fecha): array
    {
        $horarios = $bloque->horarios;
        $horario = $horarios->first(fn (BloqueHorario $h) => (int) $h->dia_semana === $fecha->dayOfWeekIso) ?? $horarios->first();
        if (! $horario instanceof BloqueHorario || ! $horario->getRawOriginal('hora_inicio') || ! $horario->getRawOriginal('hora_fin')) {
            return [self::HORAS_SIN_HORARIO, true];
        }
        $minutos = $horario->hora_inicio->copy()->setDate(2000, 1, 1)->diffInMinutes($horario->hora_fin->copy()->setDate(2000, 1, 1), false);
        if ($minutos <= 0) {
            return [self::HORAS_SIN_HORARIO, true];
        }

        return [round($minutos / 60, 2), false];
    }
}
