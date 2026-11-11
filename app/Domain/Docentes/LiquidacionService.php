<?php

namespace App\Domain\Docentes;

use App\Domain\Finanzas\GastoService;
use App\Domain\Finanzas\PagosDocenteService;
use App\Models\AjusteHorasDocente;
use App\Models\Auditoria;
use App\Models\Bloque;
use App\Models\DocenteTarifa;
use App\Models\LiquidacionDocenteMensual;
use App\Models\Profesor;
use App\Models\Sede;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Liquidación docente mensual con dos modalidades, elegida por docente:
 *  - «hora»: horas dictadas (asistencias tomadas + ajustes) × valor hora de cada sede.
 *  - «porcentaje»: el abono por sede que ya calcula Finanzas sobre las cuotas cobradas
 *    (PagosDocenteService + pago_detalles.abono_profesor). Se lee, no se modifica.
 * Siempre se muestran las dos para comparar. Estados: borrador → aprobada → pagada.
 * Al marcarla pagada se registra el gasto de sueldo con GastoService (API pública de Finanzas)
 * si quien la paga puede registrar gastos en esa sede.
 *
 * Permisos: `liquidaciones.view` / `liquidaciones.manage` con alcance de sede (hay que
 * alcanzar todas las sedes donde da clase el docente para gestionarla).
 */
class LiquidacionService
{
    public function __construct(private HorasDictadasService $horas, private PagosDocenteService $pagosDocente) {}

    /** @return list<int> */
    public function sedesDelProfesor(Profesor $profesor): array
    {
        return $this->horas->bloquesQueDicta($profesor)->pluck('sede_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    public function puede(User $user, string $permiso, Profesor $profesor): bool
    {
        $alcance = $user->acceso()->alcance($permiso);
        if ($alcance->esGlobal()) {
            return true;
        }
        $sedes = $this->sedesDelProfesor($profesor);
        if ($sedes === []) {
            return false;
        }
        $incluidas = array_filter($sedes, fn ($s) => $alcance->incluyeSede($s));

        return $permiso === 'liquidaciones.view' ? $incluidas !== [] : count($incluidas) === count($sedes);
    }

    /** Docente de la cuenta (por persona o por usuario). */
    public function profesorDe(User $user): ?Profesor
    {
        return Profesor::query()->where(fn ($q) => $q->where('user_id', $user->id)->when($user->persona_id, fn ($q2) => $q2->orWhere('persona_id', $user->persona_id)))->first();
    }

    /** @return Collection<int, Profesor> */
    public function profesoresVisibles(User $user): Collection
    {
        $alcance = $user->acceso()->alcance('liquidaciones.view');
        $q = Profesor::query()->where('activo', true)->orderBy('nombre');
        if (! $alcance->esGlobal()) {
            $sedes = $alcance->sedeIds() ?: [0];
            $bloques = Bloque::query()->whereIn('sede_id', $sedes)->pluck('id')->all() ?: [0];
            $q->where(fn ($w) => $w->whereHas('bloques', fn ($b) => $b->whereIn('bloques.id', $bloques))
                ->orWhereIn('id', Bloque::query()->whereIn('id', $bloques)->whereNotNull('profesor_id')->pluck('profesor_id')->all() ?: [0]));
        }

        return $q->get();
    }

    public function modalidad(Profesor $profesor): string
    {
        return DocenteTarifa::query()->where('profesor_id', $profesor->id)->whereNull('sede_id')->value('modalidad') ?? 'porcentaje';
    }

    /** @return array{general: float, por_sede: array<int, float>} */
    public function valoresHora(Profesor $profesor): array
    {
        $filas = DocenteTarifa::query()->where('profesor_id', $profesor->id)->get(['sede_id', 'valor_hora']);
        $general = (float) ($filas->firstWhere('sede_id', null)->valor_hora ?? 0);
        $porSede = [];
        foreach ($filas->whereNotNull('sede_id') as $f) {
            $porSede[(int) $f->sede_id] = (float) $f->valor_hora;
        }

        return ['general' => $general, 'por_sede' => $porSede];
    }

    /** @return array<string, mixed> */
    public function reglasTarifa(): array
    {
        return [
            'modalidad' => ['required', Rule::in(array_keys(DocenteTarifa::MODALIDADES))],
            'valor_hora' => 'nullable|numeric|min:0|max:10000000',
            'sedes' => 'nullable|array',
            'sedes.*' => 'nullable|numeric|min:0|max:10000000',
        ];
    }

    /**
     * @param  array<string, mixed>  $datos  modalidad, valor_hora (general) y sedes[sede_id] => valor
     */
    public function guardarTarifa(User $por, Profesor $profesor, array $datos): void
    {
        abort_unless($this->puede($por, 'liquidaciones.manage', $profesor), 403, 'No podés configurar el valor hora de este docente.');
        DB::transaction(function () use ($profesor, $datos) {
            DocenteTarifa::query()->updateOrCreate(['profesor_id' => $profesor->id, 'sede_id' => null], ['modalidad' => $datos['modalidad'], 'valor_hora' => (float) ($datos['valor_hora'] ?? 0)]);
            foreach ((array) ($datos['sedes'] ?? []) as $sedeId => $valor) {
                if ($valor === null || $valor === '') {
                    DocenteTarifa::query()->where('profesor_id', $profesor->id)->where('sede_id', (int) $sedeId)->delete();

                    continue;
                }
                if (Sede::query()->whereKey((int) $sedeId)->exists()) {
                    DocenteTarifa::query()->updateOrCreate(['profesor_id' => $profesor->id, 'sede_id' => (int) $sedeId], ['modalidad' => $datos['modalidad'], 'valor_hora' => (float) $valor]);
                }
            }
        });
        Auditoria::registrar('tarifa_docente', $profesor, null, $datos);
    }

    /** Abono por porcentaje del mes (lo que ya liquida Finanzas por cuotas cobradas). */
    public function montoPorcentaje(Profesor $profesor, int $anio, int $mes): float
    {
        $inicio = Carbon::create($anio, $mes, 1);
        $q = $this->pagosDocente->lineas($profesor, ['desde' => $inicio->toDateString(), 'hasta' => $inicio->copy()->endOfMonth()->toDateString()]);
        if (! $q) {
            return 0.0;
        }

        return round((float) $q->reorder()->whereNull('pagos.anulado_at')->sum('pago_detalles.abono_profesor'), 2);
    }

    /**
     * Cálculo completo del mes, sin guardar (ambas modalidades).
     *
     * @return array<string, mixed>
     */
    public function calcular(Profesor $profesor, int $anio, int $mes): array
    {
        $h = $this->horas->calcular($profesor, $anio, $mes);
        $valores = $this->valoresHora($profesor);
        $montoHoras = 0.0;
        $porSede = [];
        foreach ($h['por_sede'] as $k => $s) {
            $valor = $valores['por_sede'][(int) $s['sede_id']] ?? $valores['general'];
            $horasSede = max(0, $s['horas_dictadas'] + $s['horas_ajuste']);
            $monto = round($horasSede * $valor, 2);
            $montoHoras += $monto;
            $porSede[] = $s + ['horas' => round($horasSede, 2), 'valor_hora' => $valor, 'monto' => $monto];
        }
        $modalidad = $this->modalidad($profesor);
        $porcentaje = $this->montoPorcentaje($profesor, $anio, $mes);

        return [
            'modalidad' => $modalidad,
            'horas_dictadas' => $h['horas_dictadas'],
            'horas_ajuste' => $h['horas_ajuste'],
            'monto_horas' => round($montoHoras, 2),
            'monto_porcentaje' => $porcentaje,
            'monto_total' => $modalidad === 'hora' ? round($montoHoras, 2) : $porcentaje,
            'detalle' => [
                'clases' => $h['clases'],
                'ajustes' => $h['ajustes'],
                'por_sede' => $porSede,
                'sin_horario' => count(array_filter($h['clases'], fn ($c) => $c['sin_horario'])),
                'valor_hora_general' => $valores['general'],
            ],
        ];
    }

    /** Crea o recalcula el borrador del mes. Una liquidación aprobada o pagada no se recalcula. */
    public function generar(User $por, Profesor $profesor, int $anio, int $mes): LiquidacionDocenteMensual
    {
        abort_unless($this->puede($por, 'liquidaciones.manage', $profesor), 403, 'No podés liquidar a este docente.');
        $existente = LiquidacionDocenteMensual::query()->where('profesor_id', $profesor->id)->where('anio', $anio)->where('mes', $mes)->first();
        if ($existente && $existente->estado !== LiquidacionDocenteMensual::BORRADOR) {
            throw ValidationException::withMessages(['estado' => 'La liquidación de ese mes ya está '.mb_strtolower($existente->etiquetaEstado()).': volvela a borrador para recalcularla.']);
        }
        $calculo = $this->calcular($profesor, $anio, $mes);
        $liq = $existente ?? new LiquidacionDocenteMensual(['profesor_id' => $profesor->id, 'anio' => $anio, 'mes' => $mes]);
        $liq->fill($calculo + ['estado' => LiquidacionDocenteMensual::BORRADOR, 'generada_por' => $por->id])->save();

        return $liq;
    }

    /** Genera los borradores de todos los docentes que la persona puede liquidar. */
    public function generarTodas(User $por, int $anio, int $mes): int
    {
        $n = 0;
        foreach ($this->profesoresVisibles($por) as $profesor) {
            if (! $this->puede($por, 'liquidaciones.manage', $profesor)) {
                continue;
            }
            $estado = LiquidacionDocenteMensual::query()->where('profesor_id', $profesor->id)->where('anio', $anio)->where('mes', $mes)->value('estado');
            if ($estado !== null && $estado !== LiquidacionDocenteMensual::BORRADOR) {
                continue;
            }
            $this->generar($por, $profesor, $anio, $mes);
            $n++;
        }

        return $n;
    }

    public function aprobar(User $por, LiquidacionDocenteMensual $liq): LiquidacionDocenteMensual
    {
        $this->autorizarGestion($por, $liq);
        abort_unless($liq->estado === LiquidacionDocenteMensual::BORRADOR, 422, 'Solo se aprueba una liquidación en preparación.');
        $liq->forceFill(['estado' => LiquidacionDocenteMensual::APROBADA, 'aprobada_por' => $por->id, 'aprobada_at' => now()])->save();
        Auditoria::registrar('aprobar_liquidacion', $liq, ['estado' => 'borrador'], ['estado' => 'aprobada', 'monto_total' => $liq->monto_total]);

        return $liq;
    }

    public function volverABorrador(User $por, LiquidacionDocenteMensual $liq): LiquidacionDocenteMensual
    {
        $this->autorizarGestion($por, $liq);
        abort_unless($liq->estado === LiquidacionDocenteMensual::APROBADA, 422, 'Solo una liquidación aprobada (y no pagada) vuelve a preparación.');
        $liq->forceFill(['estado' => LiquidacionDocenteMensual::BORRADOR, 'aprobada_por' => null, 'aprobada_at' => null])->save();
        Auditoria::registrar('reabrir_liquidacion', $liq, ['estado' => 'aprobada'], ['estado' => 'borrador']);

        return $liq;
    }

    /**
     * Marca pagada. Con $registrarGasto, deja el gasto de sueldo (por sede, con GastoService)
     * en las sedes donde quien paga puede registrar gastos; lo que no pudo registrar se informa.
     *
     * @return array{liquidacion: LiquidacionDocenteMensual, gastos: list<int>, sin_gasto: list<string>}
     */
    public function pagar(User $por, LiquidacionDocenteMensual $liq, bool $registrarGasto = true): array
    {
        $this->autorizarGestion($por, $liq);
        abort_unless($liq->estado === LiquidacionDocenteMensual::APROBADA, 422, 'Primero hay que aprobar la liquidación.');
        $gastos = [];
        $sinGasto = [];

        DB::transaction(function () use ($por, $liq, $registrarGasto, &$gastos, &$sinGasto) {
            if ($registrarGasto && $liq->monto_total > 0) {
                $gastoService = app(GastoService::class);
                foreach ($this->partesPorSede($liq) as $parte) {
                    if ($parte['monto'] <= 0) {
                        continue;
                    }
                    if (! $gastoService->puedeEnAlcance($por, 'gastos.create', $parte['sede_id'])) {
                        $sinGasto[] = $parte['sede'];

                        continue;
                    }
                    $gastos[] = $gastoService->registrar([
                        'sede_id' => $parte['sede_id'],
                        'fecha' => now()->toDateString(),
                        'tipo' => 'sueldo',
                        'subtipo' => 'liquidacion_docente',
                        'descripcion' => mb_substr('Liquidación docente '.$liq->profesor?->nombre.' · '.$liq->periodo().' · '.$liq->numeroRecibo(), 0, 255),
                        'monto' => $parte['monto'],
                        'proveedor' => $liq->profesor?->nombre,
                    ], $por)->id;
                }
            }
            $liq->forceFill(['estado' => LiquidacionDocenteMensual::PAGADA, 'pagada_por' => $por->id, 'pagada_at' => now(), 'gasto_ids' => $gastos ?: null])->save();
        });
        Auditoria::registrar('pagar_liquidacion', $liq, ['estado' => 'aprobada'], ['estado' => 'pagada', 'gastos' => $gastos, 'sin_gasto' => $sinGasto]);

        return ['liquidacion' => $liq, 'gastos' => $gastos, 'sin_gasto' => $sinGasto];
    }

    /**
     * Cómo se reparte el monto entre sedes para el gasto: por horas, el monto de cada sede;
     * por porcentaje, en la única sede si da clase en una sola (si no, gasto general).
     *
     * @return list<array{sede_id: ?int, sede: string, monto: float}>
     */
    public function partesPorSede(LiquidacionDocenteMensual $liq): array
    {
        $porSede = collect($liq->detalle['por_sede'] ?? []);
        if ($liq->modalidad === 'hora' && $porSede->isNotEmpty()) {
            return $porSede->map(fn ($s) => ['sede_id' => $s['sede_id'] ? (int) $s['sede_id'] : null, 'sede' => $s['sede'], 'monto' => (float) $s['monto']])->values()->all();
        }
        $sedes = $liq->profesor ? $this->sedesDelProfesor($liq->profesor) : [];
        $unica = count($sedes) === 1 ? $sedes[0] : null;

        return [['sede_id' => $unica, 'sede' => $unica ? (string) Sede::query()->whereKey($unica)->value('nombre') : 'Toda la escuela', 'monto' => (float) $liq->monto_total]];
    }

    /** @return array<string, mixed> */
    public function reglasAjuste(): array
    {
        return [
            'profesor_id' => 'required|integer|exists:profesores,id',
            'bloque_id' => 'nullable|integer|exists:bloques,id',
            'sede_id' => 'nullable|integer|exists:sedes,id',
            'fecha' => 'required|date',
            'tipo' => ['required', Rule::in(array_keys(AjusteHorasDocente::TIPOS))],
            'horas' => 'required|numeric|min:0.25|max:24',
            'motivo' => 'nullable|string|max:255',
        ];
    }

    /** @param  array<string, mixed>  $datos */
    public function agregarAjuste(User $por, array $datos): AjusteHorasDocente
    {
        $profesor = Profesor::query()->findOrFail((int) $datos['profesor_id']);
        abort_unless($this->puede($por, 'liquidaciones.manage', $profesor), 403, 'No podés cargar ajustes de este docente.');
        $fecha = Carbon::parse($datos['fecha']);
        $this->asegurarMesAbierto($profesor, $fecha);
        $sedeId = $datos['sede_id'] ?? null;
        if (! $sedeId && ! empty($datos['bloque_id'])) {
            $sedeId = Bloque::query()->whereKey((int) $datos['bloque_id'])->value('sede_id');
        }
        $ajuste = AjusteHorasDocente::create([
            'profesor_id' => $profesor->id,
            'bloque_id' => $datos['bloque_id'] ?? null,
            'sede_id' => $sedeId,
            'fecha' => $fecha->toDateString(),
            'tipo' => $datos['tipo'],
            'horas' => abs((float) $datos['horas']),
            'motivo' => $datos['motivo'] ?? null,
            'user_id' => $por->id,
        ]);
        Auditoria::registrar('created', $ajuste, null, $ajuste->only(['profesor_id', 'fecha', 'tipo', 'horas', 'motivo']));

        return $ajuste;
    }

    public function eliminarAjuste(User $por, AjusteHorasDocente $ajuste): void
    {
        $profesor = $ajuste->profesor()->firstOrFail();
        abort_unless($this->puede($por, 'liquidaciones.manage', $profesor), 403);
        $this->asegurarMesAbierto($profesor, $ajuste->fecha);
        Auditoria::registrar('deleted', $ajuste, $ajuste->only(['profesor_id', 'fecha', 'tipo', 'horas', 'motivo']), null);
        $ajuste->delete();
    }

    private function asegurarMesAbierto(Profesor $profesor, Carbon $fecha): void
    {
        $estado = LiquidacionDocenteMensual::query()->where('profesor_id', $profesor->id)->where('anio', $fecha->year)->where('mes', $fecha->month)->value('estado');
        if ($estado !== null && $estado !== LiquidacionDocenteMensual::BORRADOR) {
            throw ValidationException::withMessages(['fecha' => 'La liquidación de ese mes ya está aprobada o pagada: no se pueden cambiar sus horas.']);
        }
    }

    private function autorizarGestion(User $user, LiquidacionDocenteMensual $liq): void
    {
        $profesor = $liq->profesor()->firstOrFail();
        abort_unless($this->puede($user, 'liquidaciones.manage', $profesor), 403, 'No podés gestionar esta liquidación.');
    }

    /** ¿Puede ver esta liquidación? (gestión o el propio docente si ya está aprobada). */
    public function puedeVer(User $user, LiquidacionDocenteMensual $liq): bool
    {
        $profesor = $liq->profesor()->first();
        if (! $profesor) {
            return false;
        }
        if ($this->puede($user, 'liquidaciones.view', $profesor)) {
            return true;
        }
        $propio = $this->profesorDe($user);

        return $propio !== null && $propio->id === $profesor->id && $liq->estado !== LiquidacionDocenteMensual::BORRADOR;
    }

    /** @return Collection<int, LiquidacionDocenteMensual> */
    public function delDocente(Profesor $profesor): Collection
    {
        return LiquidacionDocenteMensual::query()->where('profesor_id', $profesor->id)
            ->whereIn('estado', [LiquidacionDocenteMensual::APROBADA, LiquidacionDocenteMensual::PAGADA])
            ->orderByDesc('anio')->orderByDesc('mes')->get();
    }

    /** @return array<string, mixed> */
    public function aJson(LiquidacionDocenteMensual $l, bool $conDetalle = false): array
    {
        return [
            'id' => $l->id,
            'numero' => $l->numeroRecibo(),
            'anio' => $l->anio,
            'mes' => $l->mes,
            'periodo' => $l->periodo(),
            'estado' => $l->estado,
            'estado_nombre' => $l->etiquetaEstado(),
            'modalidad' => $l->modalidad,
            'modalidad_nombre' => DocenteTarifa::MODALIDADES[$l->modalidad] ?? $l->modalidad,
            'horas_dictadas' => $l->horas_dictadas,
            'horas_ajuste' => $l->horas_ajuste,
            'horas_total' => $l->horasTotales(),
            'monto_horas' => $l->monto_horas,
            'monto_porcentaje' => $l->monto_porcentaje,
            'monto_total' => $l->monto_total,
            'aprobada_at' => $l->aprobada_at?->toIso8601String(),
            'pagada_at' => $l->pagada_at?->toIso8601String(),
        ] + ($conDetalle ? ['detalle' => $l->detalle] : []);
    }
}
