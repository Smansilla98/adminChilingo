<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Inventario\PrestamoService;
use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\InventarioItem;
use App\Models\InventarioPrestamo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Préstamos de instrumentos (panel de inventario y app del alumno). Mismas reglas que la web.
 */
class PrestamoController extends Controller
{
    public function index(Request $request, PrestamoService $prestamos): JsonResponse
    {
        $request->validate(['estado' => 'nullable|in:prestado,vencido,devuelto', 'sede_id' => 'nullable|integer|exists:sedes,id']);
        $query = $prestamos->consulta($request->input('estado'), $request->integer('sede_id') ?: null);
        // Quien ve el inventario solo ve los préstamos de los ítems de su alcance.
        $query->whereHas('item', fn ($q) => $request->user()->acceso()->alcance('inventario.view')->aplicarPorSede($q));
        $pagina = $query->paginate(40);

        return response()->json([
            'data' => collect($pagina->items())->map(fn (InventarioPrestamo $p) => PrestamoService::presentar($p))->values(),
            'meta' => ['current_page' => $pagina->currentPage(), 'last_page' => $pagina->lastPage(), 'total' => $pagina->total()],
        ]);
    }

    public function store(Request $request, InventarioItem $item, PrestamoService $prestamos): JsonResponse
    {
        abort_unless($request->user()->acceso()->puedeEnSede('inventario.update', $item->sede_id ? (int) $item->sede_id : null), 403, 'No podés prestar inventario de esa sede.');
        $datos = $request->validate(PrestamoService::reglas());
        $prestamo = $prestamos->prestar($item, $datos, $request->user());

        return response()->json(['data' => PrestamoService::presentar($prestamo->load('item.sede', 'alumno', 'persona'))], 201);
    }

    public function devolver(Request $request, InventarioPrestamo $prestamo, PrestamoService $prestamos): JsonResponse
    {
        $item = $prestamo->item;
        abort_unless($request->user()->acceso()->puedeEnSede('inventario.update', $item->sede_id ? (int) $item->sede_id : null), 403, 'No podés registrar devoluciones de esa sede.');
        $datos = $request->validate(PrestamoService::reglasDevolucion());
        $prestamo = $prestamos->devolver($prestamo, $datos, $request->user());

        return response()->json(['data' => PrestamoService::presentar($prestamo->load('item.sede', 'alumno', 'persona'))]);
    }

    /** Préstamos del alumno (o de la persona) que usa la app. */
    public function mios(Request $request): JsonResponse
    {
        $user = $request->user();
        $alumnoIds = Alumno::query()->where('activo', true)
            ->where(fn ($q) => $q->where('persona_id', $user->persona_id ?: 0)->orWhere('user_id', $user->id))
            ->pluck('id')->all();
        $prestamos = InventarioPrestamo::query()->with(['item.sede', 'alumno', 'persona'])
            ->where(fn ($q) => $q->whereIn('alumno_id', $alumnoIds ?: [0])->orWhere('persona_id', $user->persona_id ?: 0))
            ->orderByRaw('devuelto_el IS NULL DESC')->orderByDesc('entregado_el')->limit(100)->get();

        return response()->json(['data' => $prestamos->map(fn (InventarioPrestamo $p) => PrestamoService::presentar($p))->values()]);
    }
}
