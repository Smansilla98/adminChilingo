<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Compras\CompraService;
use App\Domain\Compras\PlanComprasService;
use App\Http\Controllers\Controller;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraItem;
use App\Models\Sede;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Órdenes de compra y plan de compras. Mismas reglas que el panel web
 * (CompraService, PlanComprasService).
 */
class CompraController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = OrdenCompra::query()->with(['sede:id,nombre', 'creador:id,name'])->withCount('items')->orderByDesc('created_at');
        $request->user()->acceso()->alcance('compras.view')->aplicarPorSede($query);
        foreach (['sede_id', 'estado', 'motivo'] as $campo) {
            if ($request->filled($campo)) {
                $query->where($campo, $request->input($campo));
            }
        }
        if ($request->filled('q')) {
            $t = '%'.trim((string) $request->input('q')).'%';
            $query->where(fn ($w) => $w->where('justificacion', 'like', $t)->orWhereHas('items', fn ($i) => $i->where('descripcion', 'like', $t)));
        }
        $pagina = $query->paginate(30);

        return response()->json([
            'data' => collect($pagina->items())->map(fn (OrdenCompra $o) => $this->orden($request, $o, false))->values(),
            'meta' => ['current_page' => $pagina->currentPage(), 'last_page' => $pagina->lastPage(), 'total' => $pagina->total()],
        ]);
    }

    public function show(Request $request, OrdenCompra $orden, CompraService $compras): JsonResponse
    {
        $compras->asegurarAlcance($request->user(), 'compras.view', (int) $orden->sede_id);

        return response()->json(['data' => $this->orden($request, $orden->load(['sede', 'creador', 'items']), true)]);
    }

    public function store(Request $request, CompraService $compras): JsonResponse
    {
        $datos = $request->validate($compras->reglas() + $compras->reglasItems());
        $items = $datos['items'];
        unset($datos['items']);
        $orden = $compras->guardar(null, $datos, $items, $request->user());

        return response()->json(['data' => $this->orden($request, $orden->load(['sede', 'creador', 'items']), true)], 201);
    }

    public function update(Request $request, OrdenCompra $orden, CompraService $compras): JsonResponse
    {
        $compras->asegurarAlcance($request->user(), 'compras.create', (int) $orden->sede_id);
        $datos = $request->validate($compras->reglas() + $compras->reglasItems());
        $items = $datos['items'];
        unset($datos['items']);
        $compras->guardar($orden, $datos, $items, $request->user());

        return response()->json(['data' => $this->orden($request, $orden->fresh(['sede', 'creador', 'items']), true)]);
    }

    public function estado(Request $request, OrdenCompra $orden, CompraService $compras): JsonResponse
    {
        $data = $request->validate(['estado' => 'required|in:'.implode(',', array_keys(OrdenCompra::ESTADOS))]);
        $compras->cambiarEstado($orden, $data['estado'], $request->user());

        return response()->json(['data' => $this->orden($request, $orden->fresh(['sede', 'creador', 'items']), true)]);
    }

    public function destroy(Request $request, OrdenCompra $orden, CompraService $compras): JsonResponse
    {
        $compras->eliminar($orden, $request->user());

        return response()->json(['ok' => true]);
    }

    public function plan(Request $request, PlanComprasService $plan): JsonResponse
    {
        return response()->json([
            'ratio_objetivo' => PlanComprasService::RATIO_OBJETIVO,
            'parches_base' => PlanComprasService::PARCHES_BASE,
            'sedes' => collect($plan->calcular($request->user()))->map(fn ($d) => ['sede' => ['id' => $d['sede']->id, 'nombre' => $d['sede']->nombre]] + collect($d)->except('sede')->all())->values(),
        ]);
    }

    public function catalogo(Request $request): JsonResponse
    {
        $sedes = Sede::query()->orderBy('nombre');
        $request->user()->acceso()->alcance('compras.create')->aplicarPorSede($sedes, 'id');

        return response()->json([
            'estados' => OrdenCompra::ESTADOS,
            'motivos' => OrdenCompra::MOTIVOS,
            'sedes' => $request->user()->acceso()->puede('compras.create') ? $sedes->get(['id', 'nombre']) : [],
        ]);
    }

    /** @return array<string, mixed> */
    private function orden(Request $request, OrdenCompra $o, bool $detalle): array
    {
        $user = $request->user();
        $servicio = app(CompraService::class);
        $puedeCrear = $servicio->puede($user, 'compras.create', (int) $o->sede_id);
        $puedeAprobar = $servicio->puede($user, 'compras.approve', (int) $o->sede_id);

        return [
            'id' => $o->id,
            'sede' => $o->sede ? ['id' => $o->sede->id, 'nombre' => $o->sede->nombre] : null,
            'motivo' => $o->motivo,
            'motivo_nombre' => OrdenCompra::MOTIVOS[$o->motivo] ?? $o->motivo,
            'estado' => $o->estado,
            'estado_nombre' => OrdenCompra::ESTADOS[$o->estado] ?? $o->estado,
            'fecha_objetivo' => $o->fecha_objetivo?->toDateString(),
            'justificacion' => $o->justificacion,
            'total_estimado' => (float) $o->total_estimado,
            'cantidad_items' => $o->items_count ?? ($o->relationLoaded('items') ? $o->items->count() : null),
            'creado_por' => $o->creador?->name,
            'creado_at' => $o->created_at?->toIso8601String(),
            'items' => $detalle ? $o->items->map(fn (OrdenCompraItem $i) => [
                'id' => $i->id,
                'tipo' => $i->tipo,
                'familia' => $i->familia,
                'descripcion' => $i->descripcion,
                'marca' => $i->marca,
                'modelo' => $i->modelo,
                'medida' => $i->medida,
                'cantidad' => (float) $i->cantidad,
                'unidad' => $i->unidad,
                'precio_estimado' => $i->precio_estimado !== null ? (float) $i->precio_estimado : null,
                'subtotal_estimado' => (float) $i->subtotal_estimado,
            ])->values() : null,
            'acciones' => $detalle ? [
                'editar' => $puedeCrear,
                'eliminar' => $puedeCrear,
                'estados_posibles' => $puedeCrear ? array_values(array_filter(array_keys(OrdenCompra::ESTADOS), fn ($e) => $e !== $o->estado && ($puedeAprobar || ! in_array($e, ['aprobada', 'recibida'], true)))) : [],
            ] : null,
        ];
    }
}
