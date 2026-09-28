<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\VillaGesell\VillaGesellAdmin;
use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Bloque;
use App\Models\Profesor;
use App\Models\Sede;
use App\Models\VillaGesellDia;
use App\Models\VillaGesellGasto;
use App\Models\VillaGesellInscripto;
use App\Models\VillaGesellInsumo;
use App\Models\VillaGesellTocada;
use App\Services\VillaGesellGiraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gira a Villa Gesell: datos y plan económico, inscriptos (plaza / lista de espera,
 * aporte, remera, tambores), calendario de tocadas, gastos e insumos. Mismas reglas
 * que el panel web (VillaGesellAdmin + VillaGesellGiraService).
 */
class VillaGesellController extends Controller
{
    public function __construct(private VillaGesellGiraService $gira, private VillaGesellAdmin $admin) {}

    public function resumen(): JsonResponse
    {
        $this->gira->asegurarDias();

        return response()->json(['config' => $this->config(), 'plan' => $this->gira->plan()]);
    }

    public function actualizarConfig(Request $request): JsonResponse
    {
        $this->admin->actualizarConfig($request->validate($this->admin->reglasConfig()));

        return response()->json(['config' => $this->config(), 'plan' => $this->gira->plan()]);
    }

    public function catalogo(): JsonResponse
    {
        return response()->json([
            'estados_pago' => VillaGesellInscripto::ESTADOS_PAGO,
            'talles' => array_keys(VillaGesellInscripto::TALLES),
            'tambores' => VillaGesellInscripto::TAMBORES,
            'origenes_tambor' => VillaGesellInscripto::ORIGENES_TAMBOR,
            'categorias_insumo' => VillaGesellInsumo::CATEGORIAS,
            'tipos_gasto' => VillaGesellGasto::TIPOS,
            'modos_gasto' => VillaGesellGasto::MODOS,
            'sedes' => Sede::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'bloques' => Bloque::query()->where('activo', true)->with(['sede:id,nombre', 'profesor:id,nombre'])->orderBy('nombre')->get()
                ->map(fn (Bloque $b) => ['id' => $b->id, 'nombre' => $b->nombre, 'sede' => $b->sede?->nombre, 'profesor' => $b->profesor?->nombre])->values(),
            'profesores' => Profesor::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    // ───────── Inscriptos ─────────

    public function inscriptos(Request $request): JsonResponse
    {
        $estado = (string) $request->input('estado', '');
        $lista = VillaGesellInscripto::query()->with('alumno.sede')
            ->when($estado !== '' && array_key_exists($estado, VillaGesellInscripto::ESTADOS_PAGO), fn ($q) => $q->where('estado_pago', $estado))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('alumno', fn ($a) => $a->where('nombre_apellido', 'like', '%'.trim((string) $request->input('q')).'%')))
            ->when($request->input('lista') === 'espera', fn ($q) => $q->where('lista_espera', true))
            ->when($request->input('lista') === 'plaza', fn ($q) => $q->where('lista_espera', false))
            ->orderByRaw('lista_espera asc')->orderByRaw('plaza is null')->orderBy('plaza')->get();

        return response()->json(['data' => $lista->map(fn (VillaGesellInscripto $i) => $this->presentarInscripto($i))->values()]);
    }

    /** @return array<string, mixed> */
    private function presentarInscripto(VillaGesellInscripto $inscripto): array
    {
        $inscripto->loadMissing('alumno.sede');

        return [
            'id' => $inscripto->id,
            'alumno' => $inscripto->alumno ? ['id' => $inscripto->alumno->id, 'nombre' => $inscripto->alumno->nombre_apellido, 'sede' => $inscripto->alumno->sede?->nombre, 'persona_id' => $inscripto->alumno->persona_id] : null,
            'estado_pago' => $inscripto->estado_pago,
            'estado_pago_nombre' => $inscripto->etiquetaPago(),
            'monto_esperado' => (float) $inscripto->monto_esperado,
            'monto_pagado' => (float) $inscripto->monto_pagado,
            'saldo' => round($inscripto->saldo(), 2),
            'plaza' => $inscripto->plaza,
            'lista_espera' => (bool) $inscripto->lista_espera,
            'fecha_desde' => $inscripto->fecha_desde?->toDateString(),
            'fecha_hasta' => $inscripto->fecha_hasta?->toDateString(),
            'dias' => $inscripto->diasUtilizados(),
            'talle_remera' => $inscripto->talle_remera,
            'tambor_principal' => $inscripto->tambor_principal,
            'tambor_secundario' => $inscripto->tambor_secundario,
            'tambor_terciario' => $inscripto->tambor_terciario,
            'tambor_principal_origen' => $inscripto->tambor_principal_origen,
            'tambor_secundario_origen' => $inscripto->tambor_secundario_origen,
            'tambor_terciario_origen' => $inscripto->tambor_terciario_origen,
            'notas' => $inscripto->notas,
        ];
    }

    public function verInscripto(VillaGesellInscripto $inscripto): JsonResponse
    {
        return response()->json(['data' => $this->presentarInscripto($inscripto)]);
    }

    /** Valores sugeridos para una inscripción nueva (plaza libre, fechas y aporte). */
    public function nuevaInscripcion(): JsonResponse
    {
        $i = $this->admin->nuevaInscripcion();

        return response()->json(['data' => [
            'plaza' => $i->plaza,
            'fecha_desde' => $i->fecha_desde?->toDateString(),
            'fecha_hasta' => $i->fecha_hasta?->toDateString(),
            'monto_esperado' => (float) $i->monto_esperado,
            'valor_por_dia' => $this->gira->config()->valorPorDia(),
        ]]);
    }

    public function inscribir(Request $request): JsonResponse
    {
        $i = $this->admin->guardarInscripcion(null, $request->validate($this->admin->reglasInscripcion()), $request->boolean('lista_espera'), $request->boolean('calcular_aporte'));

        return response()->json(['data' => $this->presentarInscripto($i)], 201);
    }

    public function actualizarInscripcion(Request $request, VillaGesellInscripto $inscripto): JsonResponse
    {
        $this->admin->guardarInscripcion($inscripto, $request->validate($this->admin->reglasInscripcion($inscripto->id)), $request->boolean('lista_espera'), $request->boolean('calcular_aporte'));

        return response()->json(['data' => $this->presentarInscripto($inscripto->fresh())]);
    }

    public function eliminarInscripcion(VillaGesellInscripto $inscripto): JsonResponse
    {
        $inscripto->delete();

        return response()->json(['ok' => true]);
    }

    /** Alumnos activos que todavía no están inscriptos (para elegir). */
    public function alumnosDisponibles(Request $request): JsonResponse
    {
        $ocupados = VillaGesellInscripto::query()->when($request->filled('excepto'), fn ($q) => $q->where('alumno_id', '!=', $request->integer('excepto')))->pluck('alumno_id');
        $q = Alumno::query()->with('sede:id,nombre')->whereNotIn('id', $ocupados)->where('activo', true)->orderBy('nombre_apellido');
        if ($request->filled('q')) {
            $t = '%'.trim((string) $request->input('q')).'%';
            $q->where(fn ($w) => $w->where('nombre_apellido', 'like', $t)->orWhere('dni', 'like', $t));
        }

        return response()->json(['data' => $q->limit(40)->get()->map(fn (Alumno $a) => ['id' => $a->id, 'nombre' => $a->nombre_apellido, 'sede' => $a->sede?->nombre])->values()]);
    }

    public function alumnoRapido(Request $request): JsonResponse
    {
        $data = $request->validate($this->admin->reglasAlumnoRapido(), ['dni.unique' => 'Ese DNI ya está cargado en el padrón.']);
        ['alumno' => $a, 'bloque' => $b, 'profesor' => $p] = $this->admin->altaRapidaAlumno($data);

        return response()->json(['data' => ['id' => $a->id, 'nombre' => $a->nombre_apellido, 'dni' => $a->dni, 'bloque' => $b?->nombre, 'profesor' => $p]], 201);
    }

    public function profesorRapido(Request $request): JsonResponse
    {
        $data = $request->validate(['nombre' => ['required', 'string', 'max:255'], 'telefono' => ['nullable', 'string', 'max:20']]);
        $p = $this->admin->altaRapidaProfesor($data['nombre'], $data['telefono'] ?? null);

        return response()->json(['data' => ['id' => $p->id, 'nombre' => $p->nombre]], 201);
    }

    public function bloqueRapido(Request $request): JsonResponse
    {
        $data = $request->validate(['nombre' => ['required', 'string', 'max:255'], 'sede_id' => ['nullable', 'exists:sedes,id'], 'profesor_id' => ['nullable', 'exists:profesores,id']]);
        $b = $this->admin->altaRapidaBloque($data['nombre'], isset($data['sede_id']) ? (int) $data['sede_id'] : null, isset($data['profesor_id']) ? (int) $data['profesor_id'] : null);

        return response()->json(['data' => ['id' => $b->id, 'nombre' => $b->nombre, 'sede' => $b->sede?->nombre, 'profesor' => $b->profesor?->nombre]], 201);
    }

    // ───────── Calendario ─────────

    public function calendario(): JsonResponse
    {
        $this->gira->asegurarDias();
        $config = $this->gira->config();
        $dias = VillaGesellDia::query()->with(['tocadas' => fn ($q) => $q->orderBy('orden')])->whereBetween('fecha', [$config->fecha_inicio, $config->fecha_fin])->orderBy('fecha')->get();

        return response()->json(['data' => $dias->map(fn (VillaGesellDia $d) => [
            'id' => $d->id,
            'fecha' => $d->fecha?->toDateString(),
            'notas' => $d->notas,
            'tocadas' => $d->tocadas->map(fn (VillaGesellTocada $t) => $this->tocada($t))->values(),
        ])->values()]);
    }

    public function generarDias(): JsonResponse
    {
        return response()->json(['agregados' => $this->gira->asegurarDias()]);
    }

    public function actualizarDia(Request $request, VillaGesellDia $dia): JsonResponse
    {
        $dia->update($request->validate(['notas' => ['nullable', 'string', 'max:400']]));

        return response()->json(['ok' => true]);
    }

    public function generarTocadas(Request $request, VillaGesellDia $dia): JsonResponse
    {
        $data = $request->validate(['cantidad' => ['required', 'integer', 'min:1', 'max:12']]);

        return response()->json(['generadas' => $this->admin->generarTocadas($dia, (int) $data['cantidad'])], 201);
    }

    public function agregarTocada(Request $request, VillaGesellDia $dia): JsonResponse
    {
        return response()->json(['data' => $this->tocada($this->admin->agregarTocada($dia, $request->validate($this->admin->reglasTocada())))], 201);
    }

    public function actualizarTocada(Request $request, VillaGesellTocada $tocada): JsonResponse
    {
        $data = $request->validate($this->admin->reglasTocada());
        $data['orden'] = isset($data['orden']) ? (int) $data['orden'] : $tocada->orden;
        $tocada->update($data);

        return response()->json(['data' => $this->tocada($tocada->fresh())]);
    }

    public function eliminarTocada(VillaGesellTocada $tocada): JsonResponse
    {
        $tocada->delete();

        return response()->json(['ok' => true]);
    }

    // ───────── Gastos e insumos ─────────

    public function gastos(): JsonResponse
    {
        $dias = $this->gira->config()->cantidadDias();

        return response()->json([
            'data' => VillaGesellGasto::query()->orderBy('tipo')->orderBy('concepto')->get()->map(fn (VillaGesellGasto $g) => $this->gasto($g, $dias))->values(),
            'plan' => $this->gira->plan(),
        ]);
    }

    public function guardarGasto(Request $request, ?VillaGesellGasto $gasto = null): JsonResponse
    {
        $datos = $this->admin->normalizarGasto($request->validate($this->admin->reglasGasto()));
        if ($gasto?->exists) {
            $gasto->update($datos);
        } else {
            $gasto = VillaGesellGasto::query()->create($datos);
        }

        return response()->json(['data' => $this->gasto($gasto->fresh(), $this->gira->config()->cantidadDias())], $gasto->wasRecentlyCreated ? 201 : 200);
    }

    public function eliminarGasto(VillaGesellGasto $gasto): JsonResponse
    {
        $gasto->delete();

        return response()->json(['ok' => true]);
    }

    public function insumos(): JsonResponse
    {
        $insumos = VillaGesellInsumo::query()->orderBy('categoria')->orderBy('nombre')->get();

        return response()->json([
            'data' => $insumos->map(fn (VillaGesellInsumo $i) => $this->insumo($i))->values(),
            'total' => round((float) $insumos->sum(fn (VillaGesellInsumo $i) => $i->costoTotal()), 2),
        ]);
    }

    public function guardarInsumo(Request $request, ?VillaGesellInsumo $insumo = null): JsonResponse
    {
        $datos = $request->validate($this->admin->reglasInsumo());
        if ($insumo?->exists) {
            $insumo->update($datos);
        } else {
            $insumo = VillaGesellInsumo::query()->create($datos);
        }

        return response()->json(['data' => $this->insumo($insumo->fresh())], $insumo->wasRecentlyCreated ? 201 : 200);
    }

    public function eliminarInsumo(VillaGesellInsumo $insumo): JsonResponse
    {
        $insumo->delete();

        return response()->json(['ok' => true]);
    }

    /** @return array<string, mixed> */
    private function config(): array
    {
        $c = $this->gira->config();

        return [
            'fecha_inicio' => $c->fecha_inicio?->toDateString(),
            'fecha_fin' => $c->fecha_fin?->toDateString(),
            'cupo_maximo' => (int) $c->cupo_maximo,
            'aporte_esperado' => (float) $c->aporte_esperado,
            'notas' => $c->notas,
            'dias' => $c->cantidadDias(),
            'valor_por_dia' => $c->valorPorDia(),
        ];
    }

    /** @return array<string, mixed> */
    private function tocada(VillaGesellTocada $t): array
    {
        return ['id' => $t->id, 'dia_id' => $t->dia_id, 'orden' => $t->orden, 'hora' => $t->hora ? substr((string) $t->hora, 0, 5) : null, 'que' => $t->que, 'donde' => $t->donde, 'notas' => $t->notas];
    }

    /** @return array<string, mixed> */
    private function gasto(VillaGesellGasto $g, int $dias): array
    {
        return [
            'id' => $g->id, 'tipo' => $g->tipo, 'tipo_nombre' => VillaGesellGasto::TIPOS[$g->tipo] ?? $g->tipo, 'concepto' => $g->concepto,
            'monto' => (float) $g->monto, 'modo' => $g->modo, 'fecha' => $g->fecha?->toDateString(), 'notas' => $g->notas,
            'proyectado' => round($g->proyectado($dias), 2),
        ];
    }

    /** @return array<string, mixed> */
    private function insumo(VillaGesellInsumo $i): array
    {
        return [
            'id' => $i->id, 'nombre' => $i->nombre, 'categoria' => $i->categoria, 'categoria_nombre' => VillaGesellInsumo::CATEGORIAS[$i->categoria] ?? $i->categoria,
            'cantidad' => (float) $i->cantidad, 'unidad' => $i->unidad, 'costo_unitario' => (float) $i->costo_unitario, 'costo_total' => round($i->costoTotal(), 2), 'notas' => $i->notas,
        ];
    }
}
