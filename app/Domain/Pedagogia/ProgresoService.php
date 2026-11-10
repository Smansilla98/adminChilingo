<?php

namespace App\Domain\Pedagogia;

use App\Models\Alumno;
use App\Models\AlumnoAnio;
use App\Models\Auditoria;
use App\Models\Bloque;
use App\Models\EvaluacionToque;
use App\Models\ProgramaRitmo;
use App\Models\ProgresoToque;
use App\Models\PromocionAnio;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Progreso del alumno por toque y año (web y API).
 *
 * Doctrina de la escuela: no hay notas ni exámenes. El estado de cada toque dice si el
 * alumno todavía no lo empezó, si lo está trabajando o si ya lo toca con el bloque; los
 * "repasos" (evaluaciones en el código) dejan una devolución. Cuando el alumno toca con el
 * bloque todos los toques obligatorios de su año, el sistema PROPONE el paso de año y lo
 * confirma una persona (docente o coordinación). Nunca bloquea material ni cursada.
 *
 * Permisos: ver = `seguimiento.view`, registrar = `seguimiento.create`, con el alcance
 * por bloque / sede de siempre.
 */
class ProgresoService
{
    public const ANIO_MAXIMO = 7;

    /** Año del programa que cursa hoy el alumno: el historial confirmado manda; si no, el de su bloque. */
    public function anioActual(Alumno $alumno): int
    {
        $abierto = AlumnoAnio::query()->where('alumno_id', $alumno->id)->whereNull('hasta')->orderByDesc('desde')->orderByDesc('id')->value('anio');
        if ($abierto) {
            return (int) $abierto;
        }

        return $this->anioPorBloque($alumno);
    }

    /**
     * Años actuales de varios alumnos en dos consultas.
     *
     * @param  Collection<int, Alumno>  $alumnos
     * @return array<int, int> alumno_id => año
     */
    public function aniosActuales(Collection $alumnos, ?int $porDefecto = null): array
    {
        $abiertos = AlumnoAnio::query()->whereIn('alumno_id', $alumnos->pluck('id')->all() ?: [0])->whereNull('hasta')
            ->orderBy('desde')->orderBy('id')->pluck('anio', 'alumno_id');
        $out = [];
        foreach ($alumnos as $a) {
            $out[$a->id] = (int) ($abiertos[$a->id] ?? $porDefecto ?? $this->anioPorBloque($a));
        }

        return $out;
    }

    private function anioPorBloque(Alumno $alumno): int
    {
        $alumno->loadMissing(['bloque:id,año', 'bloques:id,año']);
        $principal = $alumno->bloques->first(fn ($b) => (bool) ($b->pivot->es_principal ?? false)) ?? $alumno->bloque ?? $alumno->bloques->first();

        return max(1, min(self::ANIO_MAXIMO, (int) ($principal->año ?? 1)));
    }

    /**
     * Toques del programa de un año (sin los retirados), en orden.
     *
     * @return Collection<int, ProgramaRitmo>
     */
    public function toquesDelAnio(int $anio): Collection
    {
        return $this->toquesDeAnios([$anio]);
    }

    /**
     * @param  list<int>  $anios
     * @return Collection<int, ProgramaRitmo>
     */
    public function toquesDeAnios(array $anios): Collection
    {
        $q = ProgramaRitmo::query()->whereIn('año', $anios ?: [0]);
        ProgramaRitmo::soloEnPrograma($q);

        // Sin columnas pesadas (medios/contenido): solo lo que hace falta para la grilla.
        return $q->orderBy('año')->orderBy('orden')->orderBy('id')->get(['id', 'slug', 'año', 'orden', 'nombre', 'opcional']);
    }

    /** @return Builder<Alumno> */
    public function alumnosDelBloque(Bloque $bloque): Builder
    {
        return Alumno::query()->where('alumnos.activo', true)
            ->where(fn ($q) => $q->where('alumnos.bloque_id', $bloque->id)->orWhereHas('bloques', fn ($b) => $b->where('bloques.id', $bloque->id)))
            ->orderBy('nombre_apellido');
    }

    /** @return Builder<Bloque> */
    public function bloquesVisibles(User $user, string $permiso = 'seguimiento.view'): Builder
    {
        return $user->acceso()->alcance($permiso)->aplicarBloques(Bloque::query()->where('activo', true))->with('sede:id,nombre')->orderBy('nombre');
    }

    public function puedeVerBloque(User $user, Bloque $bloque): bool
    {
        return $user->acceso()->puedeEnBloque('seguimiento.view', $bloque->id, $bloque->sede_id);
    }

    public function puedeEditarBloque(User $user, Bloque $bloque): bool
    {
        return $user->acceso()->puedeEnBloque('seguimiento.create', $bloque->id, $bloque->sede_id);
    }

    /**
     * Grilla alumnos × toques del año para un bloque.
     *
     * @return array{bloque: Bloque, anio: int, toques: Collection<int, ProgramaRitmo>, alumnos: Collection<int, Alumno>, estados: array<int, array<int, array{estado: string, fecha: ?string}>>, anios: array<int, int>, propuestas: Collection<int, PromocionAnio>, resumen: array<int, array{aprobados: int, obligatorios: int}>}
     */
    public function matriz(Bloque $bloque, ?int $anio = null): array
    {
        $anio = max(1, min(self::ANIO_MAXIMO, $anio ?: (int) ($bloque->año ?: 1)));
        $toques = $this->toquesDelAnio($anio);
        $alumnos = $this->alumnosDelBloque($bloque)->get(['alumnos.id', 'alumnos.nombre_apellido', 'alumnos.bloque_id', 'alumnos.sede_id']);
        $ids = $alumnos->pluck('id')->all();

        $estados = [];
        ProgresoToque::query()->whereIn('alumno_id', $ids ?: [0])->whereIn('programa_ritmo_id', $toques->pluck('id')->all() ?: [0])
            ->get(['alumno_id', 'programa_ritmo_id', 'estado', 'fecha'])
            ->each(function (ProgresoToque $p) use (&$estados) {
                $estados[$p->alumno_id][$p->programa_ritmo_id] = ['estado' => $p->estado, 'fecha' => $p->fecha?->toDateString()];
            });

        $obligatorios = $toques->where('opcional', false)->pluck('id')->all();
        $resumen = [];
        foreach ($ids as $id) {
            $resumen[$id] = [
                'aprobados' => collect($obligatorios)->filter(fn ($t) => ($estados[$id][$t]['estado'] ?? null) === ProgresoToque::APROBADO)->count(),
                'obligatorios' => count($obligatorios),
            ];
        }

        return [
            'bloque' => $bloque,
            'anio' => $anio,
            'toques' => $toques,
            'alumnos' => $alumnos,
            'estados' => $estados,
            'anios' => $this->aniosActuales($alumnos, null),
            'propuestas' => PromocionAnio::query()->whereIn('alumno_id', $ids ?: [0])->where('estado', PromocionAnio::PROPUESTA)->with('alumno:id,nombre_apellido')->get(),
            'resumen' => $resumen,
        ];
    }

    /** @return array<string, mixed> */
    public function reglasEstado(): array
    {
        return [
            'alumno_id' => 'required|integer|exists:alumnos,id',
            'programa_ritmo_id' => 'required|integer|exists:programa_ritmos,id',
            'estado' => ['required', Rule::in(array_keys(ProgresoToque::ESTADOS))],
            'nota' => 'nullable|string|max:400',
        ];
    }

    /** Cambia el estado de un toque para un alumno del bloque y revisa si corresponde proponer el paso de año. */
    public function actualizarEstado(User $por, Bloque $bloque, Alumno $alumno, ProgramaRitmo $toque, string $estado, ?string $nota = null): ProgresoToque
    {
        abort_unless($this->puedeEditarBloque($por, $bloque), 403, 'No podés registrar el progreso de este bloque.');
        abort_unless($this->alumnosDelBloque($bloque)->whereKey($alumno->id)->exists(), 422, 'El alumno no está en este bloque.');
        if (! array_key_exists($estado, ProgresoToque::ESTADOS)) {
            throw ValidationException::withMessages(['estado' => 'Elegí un estado válido.']);
        }

        $fila = $this->guardarEstado($por, $alumno, $toque, $estado, $bloque->id, $nota);
        $this->revisarPropuesta($alumno);

        return $fila;
    }

    /**
     * Guarda varios estados de una vez (la grilla web).
     *
     * @param  array<int|string, mixed>  $estados  [alumno_id][toque_id] => estado
     */
    public function actualizarGrilla(User $por, Bloque $bloque, array $estados): int
    {
        abort_unless($this->puedeEditarBloque($por, $bloque), 403, 'No podés registrar el progreso de este bloque.');
        $alumnos = $this->alumnosDelBloque($bloque)->whereIn('alumnos.id', array_map('intval', array_keys($estados)) ?: [0])->get()->keyBy('id');
        $toqueIds = collect($estados)->flatMap(fn ($fila) => is_array($fila) ? array_keys($fila) : [])->map(fn ($id) => (int) $id)->unique()->all();
        $toques = ProgramaRitmo::query()->whereIn('id', $toqueIds ?: [0])->get(['id', 'año', 'nombre', 'opcional'])->keyBy('id');
        $actuales = ProgresoToque::query()->whereIn('alumno_id', $alumnos->keys()->all() ?: [0])->whereIn('programa_ritmo_id', $toques->keys()->all() ?: [0])
            ->get(['alumno_id', 'programa_ritmo_id', 'estado'])->mapWithKeys(fn ($p) => [$p->alumno_id.'-'.$p->programa_ritmo_id => $p->estado]);

        $cambios = 0;
        DB::transaction(function () use ($estados, $alumnos, $toques, $actuales, $por, $bloque, &$cambios) {
            foreach ($estados as $alumnoId => $fila) {
                $alumno = $alumnos->get((int) $alumnoId);
                if (! $alumno || ! is_array($fila)) {
                    continue;
                }
                $tocado = false;
                foreach ($fila as $toqueId => $estado) {
                    $toque = $toques->get((int) $toqueId);
                    if (! $toque || ! is_string($estado) || ! array_key_exists($estado, ProgresoToque::ESTADOS)) {
                        continue;
                    }
                    if (($actuales[$alumno->id.'-'.$toque->id] ?? ProgresoToque::NO_EMPEZADO) === $estado) {
                        continue;
                    }
                    $this->guardarEstado($por, $alumno, $toque, $estado, $bloque->id);
                    $cambios++;
                    $tocado = true;
                }
                if ($tocado) {
                    $this->revisarPropuesta($alumno);
                }
            }
        });

        return $cambios;
    }

    private function guardarEstado(User $por, Alumno $alumno, ProgramaRitmo $toque, string $estado, ?int $bloqueId, ?string $nota = null): ProgresoToque
    {
        $fila = ProgresoToque::query()->firstOrNew(['alumno_id' => $alumno->id, 'programa_ritmo_id' => $toque->id]);
        $fila->fill([
            'estado' => $estado,
            'fecha' => now()->toDateString(),
            'user_id' => $por->id,
            'bloque_id' => $bloqueId,
        ]);
        if ($nota !== null) {
            $fila->nota = trim($nota) ?: null;
        }
        $fila->save();

        return $fila;
    }

    /**
     * Si el alumno ya toca con el bloque los toques obligatorios de su año, deja una propuesta
     * de paso de año (una sola). Si deja de cumplirse, la propuesta pendiente se retira.
     * Una propuesta descartada no se vuelve a generar sola para el mismo año.
     */
    public function revisarPropuesta(Alumno $alumno): ?PromocionAnio
    {
        $anio = $this->anioActual($alumno);
        $pendiente = PromocionAnio::query()->where('alumno_id', $alumno->id)->where('estado', PromocionAnio::PROPUESTA)->first();
        if ($pendiente && $pendiente->anio_desde !== $anio) {
            $pendiente->delete();
            $pendiente = null;
        }

        $cumple = $anio < self::ANIO_MAXIMO && $this->completoObligatorios($alumno, $anio);
        if (! $cumple) {
            $pendiente?->delete();

            return null;
        }
        if ($pendiente) {
            return $pendiente;
        }
        $descartada = PromocionAnio::query()->where('alumno_id', $alumno->id)->where('anio_desde', $anio)->where('estado', PromocionAnio::DESCARTADA)->exists();
        if ($descartada) {
            return null;
        }

        return PromocionAnio::create(['alumno_id' => $alumno->id, 'anio_desde' => $anio, 'anio_hasta' => $anio + 1, 'estado' => PromocionAnio::PROPUESTA]);
    }

    public function completoObligatorios(Alumno $alumno, int $anio): bool
    {
        $obligatorios = $this->toquesDelAnio($anio)->where('opcional', false)->pluck('id');
        if ($obligatorios->isEmpty()) {
            return false;
        }
        $aprobados = ProgresoToque::query()->where('alumno_id', $alumno->id)->whereIn('programa_ritmo_id', $obligatorios->all())
            ->where('estado', ProgresoToque::APROBADO)->count();

        return $aprobados >= $obligatorios->count();
    }

    public function puedeResolver(User $user, Alumno $alumno): bool
    {
        return $user->acceso()->puedeSobreAlumno('seguimiento.create', $alumno);
    }

    /** Confirma el paso de año: cierra el año que cursaba y abre el siguiente en el historial. */
    public function confirmarPromocion(User $por, PromocionAnio $promocion, ?string $nota = null): PromocionAnio
    {
        $alumno = $promocion->alumno()->firstOrFail();
        abort_unless($this->puedeResolver($por, $alumno), 403, 'No podés confirmar el paso de año de este alumno.');
        abort_unless($promocion->estado === PromocionAnio::PROPUESTA, 422, 'Esta propuesta ya fue resuelta.');

        DB::transaction(function () use ($por, $promocion, $alumno, $nota) {
            $hoy = now()->toDateString();
            $abierto = AlumnoAnio::query()->where('alumno_id', $alumno->id)->whereNull('hasta')->orderByDesc('desde')->first();
            $bloquePrincipal = $alumno->bloque_id;
            if ($abierto) {
                $abierto->update(['hasta' => $hoy]);
            } else {
                AlumnoAnio::create([
                    'alumno_id' => $alumno->id,
                    'anio' => $promocion->anio_desde,
                    'bloque_id' => $bloquePrincipal,
                    'desde' => $alumno->created_at?->toDateString() ?? $hoy,
                    'hasta' => $hoy,
                ]);
            }
            AlumnoAnio::create([
                'alumno_id' => $alumno->id,
                'anio' => $promocion->anio_hasta,
                'bloque_id' => $bloquePrincipal,
                'desde' => $hoy,
                'confirmado_por' => $por->id,
                'nota' => $nota ? mb_substr(trim($nota), 0, 400) : null,
            ]);
            $promocion->update(['estado' => PromocionAnio::CONFIRMADA, 'resuelto_por' => $por->id, 'resuelto_at' => now(), 'nota' => $nota ? mb_substr(trim($nota), 0, 400) : null]);
        });
        Auditoria::registrar('confirmar_paso_de_anio', $promocion, ['anio' => $promocion->anio_desde], ['anio' => $promocion->anio_hasta, 'alumno_id' => $alumno->id]);

        return $promocion->refresh();
    }

    public function descartarPromocion(User $por, PromocionAnio $promocion, ?string $nota = null): PromocionAnio
    {
        $alumno = $promocion->alumno()->firstOrFail();
        abort_unless($this->puedeResolver($por, $alumno), 403, 'No podés resolver el paso de año de este alumno.');
        abort_unless($promocion->estado === PromocionAnio::PROPUESTA, 422, 'Esta propuesta ya fue resuelta.');
        $promocion->update(['estado' => PromocionAnio::DESCARTADA, 'resuelto_por' => $por->id, 'resuelto_at' => now(), 'nota' => $nota ? mb_substr(trim($nota), 0, 400) : null]);
        Auditoria::registrar('descartar_paso_de_anio', $promocion, null, ['alumno_id' => $alumno->id, 'anio' => $promocion->anio_desde]);

        return $promocion;
    }

    /** @return array<string, mixed> */
    public function reglasEvaluacion(): array
    {
        return [
            'alumno_id' => 'required|integer|exists:alumnos,id',
            'bloque_id' => 'required|integer|exists:bloques,id',
            'fecha' => 'required|date',
            'toques' => 'required|array|min:1',
            'toques.*' => 'integer|exists:programa_ritmos,id',
            'resultado' => ['required', Rule::in(array_keys(EvaluacionToque::RESULTADOS))],
            'devolucion' => 'nullable|string|max:4000',
            'visible_alumno' => 'nullable|boolean',
        ];
    }

    /**
     * Registra un repaso con devolución. Si «lo toca con el bloque», esos toques quedan así;
     * si «va en camino», los que no había empezado pasan a «lo está trabajando».
     *
     * @param  array<string, mixed>  $datos
     */
    public function registrarEvaluacion(User $por, array $datos): EvaluacionToque
    {
        $bloque = Bloque::query()->findOrFail((int) $datos['bloque_id']);
        abort_unless($this->puedeEditarBloque($por, $bloque), 403, 'No podés registrar repasos en este bloque.');
        $alumno = $this->alumnosDelBloque($bloque)->whereKey((int) $datos['alumno_id'])->first();
        abort_unless($alumno instanceof Alumno, 422, 'El alumno no está en este bloque.');
        $toques = ProgramaRitmo::query()->whereIn('id', array_map('intval', $datos['toques']))->get(['id', 'año', 'nombre', 'opcional']);

        return DB::transaction(function () use ($por, $datos, $bloque, $alumno, $toques) {
            $evaluacion = EvaluacionToque::create([
                'alumno_id' => $alumno->id,
                'bloque_id' => $bloque->id,
                'user_id' => $por->id,
                'fecha' => $datos['fecha'],
                'toques' => $toques->pluck('id')->values()->all(),
                'resultado' => $datos['resultado'],
                'devolucion' => isset($datos['devolucion']) ? trim((string) $datos['devolucion']) ?: null : null,
                'visible_alumno' => (bool) ($datos['visible_alumno'] ?? true),
            ]);

            $actuales = ProgresoToque::query()->where('alumno_id', $alumno->id)->whereIn('programa_ritmo_id', $toques->pluck('id')->all() ?: [0])->pluck('estado', 'programa_ritmo_id');
            foreach ($toques as $toque) {
                $actual = $actuales[$toque->id] ?? ProgresoToque::NO_EMPEZADO;
                $nuevo = match ($datos['resultado']) {
                    'lo_toca' => ProgresoToque::APROBADO,
                    'en_camino' => $actual === ProgresoToque::NO_EMPEZADO ? ProgresoToque::EN_PROCESO : null,
                    default => $actual === ProgresoToque::NO_EMPEZADO ? ProgresoToque::EN_PROCESO : null,
                };
                if ($nuevo !== null && $nuevo !== $actual) {
                    $this->guardarEstado($por, $alumno, $toque, $nuevo, $bloque->id);
                }
            }
            $this->revisarPropuesta($alumno);
            Auditoria::registrar('created', $evaluacion, null, $evaluacion->only(['alumno_id', 'bloque_id', 'fecha', 'toques', 'resultado']));

            return $evaluacion;
        });
    }

    /**
     * Todo el progreso de un alumno: por año (hasta el que cursa), repasos, historial y propuesta.
     *
     * @return array<string, mixed>
     */
    public function delAlumno(Alumno $alumno, bool $soloVisibleAlumno): array
    {
        $anio = $this->anioActual($alumno);
        $toques = $this->toquesDeAnios(range(1, $anio));
        $estados = ProgresoToque::query()->where('alumno_id', $alumno->id)->with('docente:id,name')->get()->keyBy('programa_ritmo_id');

        $porAnio = $toques->groupBy('año')->map(function (Collection $lista, $n) use ($estados) {
            $filas = $lista->map(function (ProgramaRitmo $t) use ($estados) {
                $p = $estados->get($t->id);

                return [
                    'id' => $t->id,
                    'slug' => $t->slug,
                    'nombre' => $t->nombre,
                    'opcional' => (bool) $t->opcional,
                    'estado' => $p->estado ?? ProgresoToque::NO_EMPEZADO,
                    'estado_nombre' => ProgresoToque::etiqueta($p->estado ?? null),
                    'fecha' => $p?->fecha?->toDateString(),
                    'docente' => $p?->docente?->name,
                ];
            })->values();

            return [
                'anio' => (int) $n,
                'toques' => $filas,
                'tocados' => $filas->where('estado', ProgresoToque::APROBADO)->count(),
                'en_proceso' => $filas->where('estado', ProgresoToque::EN_PROCESO)->count(),
                'total' => $filas->count(),
            ];
        })->sortKeysDesc()->values();

        $nombres = $toques->pluck('nombre', 'id');
        $evaluaciones = EvaluacionToque::query()->where('alumno_id', $alumno->id)
            ->when($soloVisibleAlumno, fn ($q) => $q->where('visible_alumno', true))
            ->with(['docente:id,name', 'bloque:id,nombre'])->orderByDesc('fecha')->orderByDesc('id')->limit(50)->get();
        $faltantes = $evaluaciones->flatMap(fn (EvaluacionToque $e) => $e->toqueIds())->unique()->diff($nombres->keys());
        if ($faltantes->isNotEmpty()) {
            $nombres = $nombres->union(ProgramaRitmo::query()->whereIn('id', $faltantes->all())->pluck('nombre', 'id'));
        }

        return [
            'alumno' => ['id' => $alumno->id, 'nombre' => $alumno->nombre_apellido],
            'anio_actual' => $anio,
            'anios' => $porAnio,
            'evaluaciones' => $evaluaciones->map(fn (EvaluacionToque $e) => [
                'id' => $e->id,
                'fecha' => $e->fecha->toDateString(),
                'resultado' => $e->resultado,
                'resultado_nombre' => $e->etiquetaResultado(),
                'devolucion' => $e->devolucion,
                'toques' => collect($e->toqueIds())->map(fn ($id) => $nombres[$id] ?? null)->filter()->values(),
                'docente' => $e->docente?->name,
                'bloque' => $e->bloque?->nombre,
                'visible_alumno' => (bool) $e->visible_alumno,
            ])->values(),
            'historial' => AlumnoAnio::query()->where('alumno_id', $alumno->id)->with(['bloque:id,nombre', 'confirmadoPor:id,name'])->orderBy('desde')->orderBy('id')->get()
                ->map(fn (AlumnoAnio $a) => [
                    'anio' => $a->anio,
                    'desde' => $a->desde->toDateString(),
                    'hasta' => $a->hasta?->toDateString(),
                    'bloque' => $a->bloque?->nombre,
                    'confirmado_por' => $a->confirmadoPor?->name,
                ])->values(),
            'propuesta' => ($p = PromocionAnio::query()->where('alumno_id', $alumno->id)->where('estado', PromocionAnio::PROPUESTA)->first())
                ? ['id' => $p->id, 'anio_desde' => $p->anio_desde, 'anio_hasta' => $p->anio_hasta, 'desde' => $p->created_at?->toDateString()]
                : null,
        ];
    }

    /**
     * Reporte para coordinación: por bloque, cuántos alumnos, cuánto del año ya tocan con
     * el bloque y cuántas propuestas de paso de año esperan confirmación. Consultas agregadas.
     *
     * @return list<array{bloque: Bloque, anio: int, alumnos: int, obligatorios: int, tocados: int, en_proceso: int, porcentaje: ?float, propuestas: int}>
     */
    public function reporte(User $user, ?int $sedeId = null): array
    {
        $bloques = $this->bloquesVisibles($user)->when($sedeId, fn ($q) => $q->where('sede_id', $sedeId))->get(['id', 'nombre', 'año', 'sede_id']);
        if ($bloques->isEmpty()) {
            return [];
        }
        $bIds = $bloques->pluck('id')->all();

        $pivote = DB::table('alumno_bloque')->join('alumnos', 'alumnos.id', '=', 'alumno_bloque.alumno_id')
            ->where('alumnos.activo', true)->whereIn('alumno_bloque.bloque_id', $bIds)->get(['alumno_bloque.bloque_id', 'alumno_bloque.alumno_id']);
        $legado = DB::table('alumnos')->where('activo', true)->whereIn('bloque_id', $bIds)->get(['bloque_id', 'id as alumno_id']);
        $alumnosPorBloque = $pivote->concat($legado)->groupBy('bloque_id')->map(fn ($g) => $g->pluck('alumno_id')->map(fn ($id) => (int) $id)->unique()->values());

        $anios = $bloques->pluck('año')->map(fn ($a) => max(1, (int) $a))->unique()->values()->all();
        $toques = $this->toquesDeAnios($anios)->groupBy('año');
        $todosAlumnos = $alumnosPorBloque->flatten()->unique()->values()->all();
        $todosToques = $toques->flatten()->pluck('id')->all();
        $progreso = ProgresoToque::query()->whereIn('alumno_id', $todosAlumnos ?: [0])->whereIn('programa_ritmo_id', $todosToques ?: [0])
            ->whereIn('estado', [ProgresoToque::APROBADO, ProgresoToque::EN_PROCESO])->get(['alumno_id', 'programa_ritmo_id', 'estado']);
        $propuestas = PromocionAnio::query()->whereIn('alumno_id', $todosAlumnos ?: [0])->where('estado', PromocionAnio::PROPUESTA)->pluck('alumno_id')->map(fn ($id) => (int) $id);

        return $bloques->map(function (Bloque $b) use ($alumnosPorBloque, $toques, $progreso, $propuestas) {
            $anio = max(1, (int) $b->año);
            $alumnos = $alumnosPorBloque->get($b->id, collect());
            $oblig = collect($toques->get($anio, collect()))->where('opcional', false)->pluck('id');
            $filas = $progreso->whereIn('alumno_id', $alumnos->all())->whereIn('programa_ritmo_id', $oblig->all());
            $tocados = $filas->where('estado', ProgresoToque::APROBADO)->count();
            $posibles = $alumnos->count() * $oblig->count();

            return [
                'bloque' => $b,
                'anio' => $anio,
                'alumnos' => $alumnos->count(),
                'obligatorios' => $oblig->count(),
                'tocados' => $tocados,
                'en_proceso' => $filas->where('estado', ProgresoToque::EN_PROCESO)->count(),
                'porcentaje' => $posibles > 0 ? round($tocados / $posibles * 100, 1) : null,
                'propuestas' => $propuestas->intersect($alumnos)->count(),
            ];
        })->values()->all();
    }
}
