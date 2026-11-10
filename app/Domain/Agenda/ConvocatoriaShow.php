<?php

namespace App\Domain\Agenda;

use App\Models\Alumno;
use App\Models\Auditoria;
use App\Models\Show;
use App\Models\ShowConfirmacion;
use App\Models\ShowEnsayo;
use App\Models\ShowEnsayoAsistencia;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Quiénes están convocados a un show, qué respondió cada uno (voy / no voy / tal vez),
 * quién fue y quién vino a cada ensayo. Lo usan el panel web, la API y los avisos.
 *
 * Convocados: los alumnos activos de los bloques del show. Si la convocatoria es abierta,
 * también quien se anotó por su cuenta (respondió desde la app o la web).
 */
class ConvocatoriaShow
{
    /** @return Collection<int, Alumno> alumnos convocados, ordenados por nombre */
    public function convocados(Show $show): Collection
    {
        $bloqueIds = $show->bloques()->pluck('bloques.id')->all();
        $query = Alumno::query()->where('activo', true)->where(function ($q) use ($bloqueIds, $show) {
            $q->whereIn('bloque_id', $bloqueIds ?: [0])
                ->orWhereHas('bloques', fn ($b) => $b->whereIn('bloques.id', $bloqueIds ?: [0]));
            if ($show->convocatoria_abierta) {
                $q->orWhereIn('id', ShowConfirmacion::query()->where('show_id', $show->id)->select('alumno_id'));
            }
        });

        return $query->with(['bloque:id,nombre', 'bloques:id,nombre'])->orderBy('nombre_apellido')->get();
    }

    public function estaConvocado(Show $show, Alumno $alumno): bool
    {
        if ($show->convocatoria_abierta) {
            return true;
        }
        $bloqueIds = $show->bloques()->pluck('bloques.id')->map(fn ($id) => (int) $id)->all();

        return array_intersect($bloqueIds, $alumno->bloqueIds()->map(fn ($id) => (int) $id)->all()) !== [];
    }

    /** @return Collection<int, Alumno> perfiles de alumno de la cuenta (activos) */
    public function alumnosDe(User $user): Collection
    {
        return Alumno::query()->where('activo', true)
            ->where(fn ($q) => $q->where('persona_id', $user->persona_id ?: 0)->orWhere('user_id', $user->id))
            ->get();
    }

    /**
     * Shows próximos a los que está convocada la cuenta (por sus bloques o por ser abiertos).
     *
     * @return Collection<int, array{show: Show, alumno: Alumno}>
     */
    public function showsDe(User $user, bool $soloProximos = true): Collection
    {
        $salida = collect();
        foreach ($this->alumnosDe($user) as $alumno) {
            $bloqueIds = $alumno->bloqueIds()->all();
            $shows = Show::query()
                ->where(fn ($q) => $q->where('convocatoria_abierta', true)
                    ->orWhereHas('bloques', fn ($b) => $b->whereIn('bloques.id', $bloqueIds ?: [0])))
                ->when($soloProximos, fn ($q) => $q->where('fecha', '>=', now()->toDateString()))
                ->orderBy('fecha')->orderBy('hora_inicio')->get();
            foreach ($shows as $show) {
                if (! $salida->has($show->id)) {
                    $salida->put($show->id, ['show' => $show, 'alumno' => $alumno]);
                }
            }
        }

        return $salida->values();
    }

    public function responder(Show $show, Alumno $alumno, string $respuesta, ?string $comentario): ShowConfirmacion
    {
        abort_unless(array_key_exists($respuesta, ShowConfirmacion::RESPUESTAS), 422, 'Respuesta inválida.');
        abort_unless($this->estaConvocado($show, $alumno), 403, 'No estás convocado a este show.');

        $conf = ShowConfirmacion::query()->updateOrCreate(
            ['show_id' => $show->id, 'alumno_id' => $alumno->id],
            ['respuesta' => $respuesta, 'comentario' => $comentario ? mb_substr(trim($comentario), 0, 255) : null, 'respondido_at' => now()],
        );
        Auditoria::registrar('show.confirmacion', $show, null, ['alumno_id' => $alumno->id, 'respuesta' => $respuesta]);

        return $conf;
    }

    /**
     * Planilla para quien organiza: cada convocado con su respuesta y si fue.
     *
     * @return list<array{alumno_id: int, nombre: string, bloque: ?string, respuesta: ?string, respuesta_texto: string, comentario: ?string, respondido_at: ?string, asistio: ?bool}>
     */
    public function planilla(Show $show): array
    {
        $confs = ShowConfirmacion::query()->where('show_id', $show->id)->get()->keyBy('alumno_id');

        return $this->convocados($show)->map(function (Alumno $a) use ($confs) {
            /** @var ShowConfirmacion|null $c */
            $c = $confs->get($a->id);

            return [
                'alumno_id' => (int) $a->id,
                'nombre' => (string) $a->nombre_apellido,
                'bloque' => $a->bloque->nombre ?? $a->bloques->first()?->nombre,
                'respuesta' => $c?->respuesta,
                'respuesta_texto' => $c?->respuesta ? ShowConfirmacion::RESPUESTAS[$c->respuesta] : 'Sin respuesta',
                'comentario' => $c?->comentario,
                'respondido_at' => $c?->respondido_at?->toIso8601String(),
                'asistio' => $c?->asistio,
            ];
        })->values()->all();
    }

    /** @return array{convocados: int, voy: int, tal_vez: int, no_voy: int, sin_respuesta: int, asistieron: int} */
    public function resumen(Show $show): array
    {
        $filas = collect($this->planilla($show));

        return [
            'convocados' => $filas->count(),
            'voy' => $filas->where('respuesta', 'voy')->count(),
            'tal_vez' => $filas->where('respuesta', 'tal_vez')->count(),
            'no_voy' => $filas->where('respuesta', 'no_voy')->count(),
            'sin_respuesta' => $filas->whereNull('respuesta')->count(),
            'asistieron' => $filas->where('asistio', true)->count(),
        ];
    }

    /**
     * Asistencia al show: alumno_id => fue (true/false). Solo para convocados.
     *
     * @param  array<int|string, bool|int|string|null>  $marcas
     */
    public function marcarAsistencia(Show $show, array $marcas): int
    {
        $validos = $this->convocados($show)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $n = 0;
        DB::transaction(function () use ($show, $marcas, $validos, &$n) {
            foreach ($marcas as $alumnoId => $fue) {
                if (! in_array((int) $alumnoId, $validos, true)) {
                    continue;
                }
                ShowConfirmacion::query()->updateOrCreate(
                    ['show_id' => $show->id, 'alumno_id' => (int) $alumnoId],
                    ['asistio' => $fue === null || $fue === '' ? null : filter_var($fue, FILTER_VALIDATE_BOOLEAN)],
                );
                $n++;
            }
        });
        Auditoria::registrar('show.asistencia', $show, null, ['marcados' => $n]);

        return $n;
    }

    /**
     * Asistencia a un ensayo: alumno_id => presente. Solo para convocados.
     *
     * @param  array<int|string, bool|int|string>  $marcas
     */
    public function marcarAsistenciaEnsayo(ShowEnsayo $ensayo, array $marcas): int
    {
        $validos = $this->convocados($ensayo->show)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $n = 0;
        DB::transaction(function () use ($ensayo, $marcas, $validos, &$n) {
            foreach ($marcas as $alumnoId => $presente) {
                if (! in_array((int) $alumnoId, $validos, true)) {
                    continue;
                }
                ShowEnsayoAsistencia::query()->updateOrCreate(
                    ['show_ensayo_id' => $ensayo->id, 'alumno_id' => (int) $alumnoId],
                    ['presente' => filter_var($presente, FILTER_VALIDATE_BOOLEAN)],
                );
                $n++;
            }
        });

        return $n;
    }

    /**
     * @return list<array{alumno_id: int, nombre: string, presente: ?bool}>
     */
    public function planillaEnsayo(ShowEnsayo $ensayo): array
    {
        $marcas = $ensayo->asistencias()->pluck('presente', 'alumno_id');

        return $this->convocados($ensayo->show)->map(fn (Alumno $a) => [
            'alumno_id' => (int) $a->id,
            'nombre' => (string) $a->nombre_apellido,
            'presente' => $marcas->has($a->id) ? (bool) $marcas->get($a->id) : null,
        ])->values()->all();
    }

    /** Cuenta activa de un alumno (para los avisos). */
    public static function cuentaDe(Alumno $alumno): ?User
    {
        return User::query()->where(fn ($q) => $q->where('persona_id', $alumno->getAttribute('persona_id') ?: 0)->orWhere('id', $alumno->getAttribute('user_id') ?: 0))
            ->where('activo', true)->first();
    }
}
