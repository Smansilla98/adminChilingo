<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Inventario\InventarioService;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\InventarioItemResource;
use App\Models\InventarioItem;
use App\Models\InventarioMovimiento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InventarioController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', InventarioItem::class);
        $query = InventarioItem::query()->with('sede:id,nombre')->orderBy('tipo')->orderBy('nombre');
        $request->user()->acceso()->alcance('inventario.view')->aplicarPorSede($query);
        foreach (['sede_id', 'tipo', 'estado'] as $f) {
            if ($request->filled($f)) {
                $query->where($f, $request->input($f));
            }
        }
        if ($request->filled('q')) {
            $t = '%'.trim((string) $request->input('q')).'%';
            $query->where(fn ($w) => $w->where('nombre', 'like', $t)->orWhere('codigo', 'like', $t)->orWhere('marca', 'like', $t));
        }

        return InventarioItemResource::collection($query->paginate(40));
    }

    public function show(InventarioItem $item): InventarioItemResource
    {
        $this->authorize('view', $item);

        return new InventarioItemResource($item->load(['sede:id,nombre', 'alumno:id,nombre_apellido', 'movimientos.autor:id,name', 'movimientos.sede:id,nombre']));
    }

    /**
     * Buscar por código escaneado (QR / etiqueta). Acepta el código o la URL pública del QR.
     */
    public function porCodigo(Request $request, string $codigo): InventarioItemResource|JsonResponse
    {
        $codigo = trim(basename(parse_url($codigo, PHP_URL_PATH) ?: $codigo));
        $item = InventarioItem::query()->where('codigo', $codigo)->first();
        if (! $item || ! $request->user()->can('view', $item)) {
            return response()->json(['message' => 'No hay un ítem con ese código en tu alcance.'], 404);
        }

        return $this->show($item);
    }

    public function store(Request $request, InventarioService $servicio): InventarioItemResource
    {
        $this->authorize('create', InventarioItem::class);
        $data = $request->validate(InventarioService::reglas());
        abort_unless($request->user()->acceso()->puedeEnSede('inventario.create', (int) $data['sede_id']), 403, 'No podés cargar inventario en esa sede.');

        return new InventarioItemResource($servicio->crear($data, $request->user()->id)->load('sede:id,nombre'));
    }

    public function update(Request $request, InventarioItem $item): InventarioItemResource
    {
        $this->authorize('update', $item);
        $data = $request->validate(InventarioService::reglas($item->id));
        abort_unless($request->user()->acceso()->puedeEnSede('inventario.update', (int) $data['sede_id']), 403);
        $item->update(InventarioService::normalizar($data));

        return new InventarioItemResource($item->fresh()->load('sede:id,nombre'));
    }

    /** Registrar movimiento / cambio de estado / mover de sede. */
    public function movimiento(Request $request, InventarioItem $item, InventarioService $servicio): InventarioItemResource
    {
        $this->authorize('update', $item);
        $data = $request->validate([
            'tipo' => 'required|in:'.implode(',', array_keys(InventarioMovimiento::TIPOS)),
            'nota' => 'nullable|string|max:400',
            'sede_id' => 'nullable|exists:sedes,id',
            'estado' => 'nullable|in:'.implode(',', array_keys(InventarioItem::ESTADOS)),
        ]);
        $item = $servicio->registrarMovimiento($item, $request->user()->id, $data['tipo'], $data['nota'] ?? null,
            ! empty($data['sede_id']) ? (int) $data['sede_id'] : null, $data['estado'] ?? null);

        return new InventarioItemResource($item->load(['sede:id,nombre', 'movimientos.autor:id,name', 'movimientos.sede:id,nombre']));
    }

    public function catalogos(): JsonResponse
    {
        return response()->json([
            'tipos' => InventarioItem::TIPOS,
            'estados' => InventarioItem::ESTADOS,
            'propietarios' => InventarioItem::PROPIETARIOS,
            'movimientos' => InventarioMovimiento::TIPOS,
        ]);
    }
}
