<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Finanzas\GastoService;
use App\Http\Controllers\Controller;
use App\Models\Bloque;
use App\Models\Gasto;
use App\Models\Sede;
use App\Services\AmbitoSedeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gastos: listado del alcance con filtros, detalle, alta (queda pendiente si no puede
 * aprobar), edición, baja y aprobación/rechazo. Mismas reglas que el panel web
 * (GastoService + GastoPolicy).
 */
class GastoController extends Controller
{
    public function index(Request $request, AmbitoSedeService $ambito): JsonResponse
    {
        $this->authorize('viewAny', Gasto::class);
        $query = Gasto::query()->with(['sede:id,nombre', 'bloque:id,nombre', 'creador:id,name'])->orderByDesc('fecha')->orderByDesc('id');
        $filtro = $ambito->idsPara($request->user(), 'gastos.view');
        if ($filtro !== null) {
            $ambito->aplicarGastos($query, $filtro);
        }
        foreach (['sede_id', 'tipo', 'estado'] as $campo) {
            if ($request->filled($campo)) {
                $query->where($campo, $request->input($campo));
            }
        }
        if ($request->filled('desde')) {
            $query->where('fecha', '>=', $request->date('desde')->toDateString());
        }
        if ($request->filled('hasta')) {
            $query->where('fecha', '<=', $request->date('hasta')->toDateString());
        }
        if ($request->filled('q')) {
            $t = '%'.trim((string) $request->input('q')).'%';
            $query->where(fn ($w) => $w->where('descripcion', 'like', $t)->orWhere('proveedor', 'like', $t)->orWhere('notas', 'like', $t));
        }
        $total = (float) (clone $query)->sum('monto');
        $pagina = $query->paginate(30);

        return response()->json([
            'data' => collect($pagina->items())->map(fn (Gasto $g) => $this->gasto($request, $g, false))->values(),
            'meta' => ['current_page' => $pagina->currentPage(), 'last_page' => $pagina->lastPage(), 'total' => $pagina->total()],
            'total_monto' => round($total, 2),
        ]);
    }

    public function show(Request $request, Gasto $gasto): JsonResponse
    {
        $this->authorize('view', $gasto);

        return response()->json(['data' => $this->gasto($request, $gasto->load(['sede', 'bloque', 'creador']), true)]);
    }

    public function store(Request $request, GastoService $gastos): JsonResponse
    {
        $this->authorize('create', Gasto::class);
        $gasto = $gastos->registrar($request->validate($gastos->reglas()), $request->user());

        return response()->json(['data' => $this->gasto($request, $gasto->load(['sede', 'bloque', 'creador']), true)], 201);
    }

    public function update(Request $request, Gasto $gasto, GastoService $gastos): JsonResponse
    {
        $this->authorize('update', $gasto);
        $gastos->actualizar($gasto, $request->validate($gastos->reglas()), $request->user());

        return response()->json(['data' => $this->gasto($request, $gasto->fresh(['sede', 'bloque', 'creador']), true)]);
    }

    public function destroy(Gasto $gasto): JsonResponse
    {
        $this->authorize('delete', $gasto);
        $gasto->delete();

        return response()->json(['ok' => true]);
    }

    public function decidir(Request $request, Gasto $gasto, GastoService $gastos): JsonResponse
    {
        $this->authorize('approve', $gasto);
        $data = $request->validate(['decision' => 'required|in:aprobado,rechazado']);
        $gastos->decidir($gasto, $data['decision'], $request->user());

        return response()->json(['data' => $this->gasto($request, $gasto->fresh(['sede', 'bloque', 'creador']), true)]);
    }

    public function catalogo(Request $request, AmbitoSedeService $ambito): JsonResponse
    {
        $this->authorize('viewAny', Gasto::class);
        $filtro = $ambito->idsPara($request->user(), 'gastos.view');
        $sedes = Sede::query()->orderBy('nombre');
        $bloques = Bloque::query()->where('activo', true)->orderBy('sede_id')->orderBy('nombre');
        if ($filtro !== null) {
            $ambito->aplicarSedesCatalogo($sedes, $filtro);
            $ambito->aplicarBloques($bloques, $filtro);
        }

        return response()->json([
            'tipos' => Gasto::TIPOS,
            'subtipos' => Gasto::SUBTIPOS,
            'estados' => GastoService::ESTADOS,
            'sedes' => $sedes->get(['id', 'nombre']),
            'bloques' => $bloques->get(['id', 'nombre', 'sede_id']),
            'puede_sin_sede' => $request->user()->acceso()->puedeGlobal('gastos.create'),
        ]);
    }

    /** @return array<string, mixed> */
    private function gasto(Request $request, Gasto $g, bool $detalle): array
    {
        $user = $request->user();

        return [
            'id' => $g->id,
            'fecha' => $g->fecha?->toDateString(),
            'tipo' => $g->tipo,
            'tipo_nombre' => Gasto::TIPOS[$g->tipo] ?? $g->tipo,
            'subtipo' => $g->subtipo,
            'subtipo_nombre' => Gasto::SUBTIPOS[$g->tipo][$g->subtipo] ?? $g->subtipo,
            'descripcion' => $g->descripcion,
            'monto' => (float) $g->monto,
            'proveedor' => $g->proveedor,
            'notas' => $detalle ? $g->notas : null,
            'estado' => $g->estado ?? 'aprobado',
            'estado_nombre' => GastoService::ESTADOS[$g->estado ?? 'aprobado'] ?? $g->estado,
            'sede' => $g->sede ? ['id' => $g->sede->id, 'nombre' => $g->sede->nombre] : null,
            'bloque' => $g->bloque ? ['id' => $g->bloque->id, 'nombre' => $g->bloque->nombre] : null,
            'creado_por' => $g->creador?->name,
            'aprobado_at' => $detalle ? $g->aprobado_at?->toIso8601String() : null,
            'aprobado_por' => $detalle && $g->aprobado_por ? \App\Models\User::query()->whereKey($g->aprobado_por)->value('name') : null,
            'acciones' => $detalle ? [
                'editar' => $user->can('update', $g),
                'eliminar' => $user->can('delete', $g),
                'aprobar' => $user->can('approve', $g) && ($g->estado ?? 'aprobado') !== 'aprobado',
                'rechazar' => $user->can('approve', $g) && ($g->estado ?? 'aprobado') !== 'rechazado',
            ] : null,
        ];
    }
}
