<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Notificaciones\DifusionService;
use App\Http\Controllers\Controller;
use App\Models\Difusion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mensajes a grupos (difusión) para la app. Mismas reglas que el panel web (DifusionService).
 */
class DifusionController extends Controller
{
    public function catalogo(Request $request, DifusionService $servicio): JsonResponse
    {
        return response()->json($servicio->catalogo($request->user()));
    }

    public function index(Request $request, DifusionService $servicio): JsonResponse
    {
        $query = Difusion::query()->with('autor:id,name')->orderByDesc('id');
        if (! $request->user()->acceso()->puedeGlobal('difusion.send')) {
            $query->where('user_id', $request->user()->id);
        }
        $pagina = $query->paginate(30);

        return response()->json([
            'data' => collect($pagina->items())->map(fn (Difusion $d) => $servicio->presentar($d))->values(),
            'meta' => ['current_page' => $pagina->currentPage(), 'last_page' => $pagina->lastPage(), 'total' => $pagina->total()],
        ]);
    }

    public function show(Request $request, Difusion $difusion, DifusionService $servicio): JsonResponse
    {
        abort_unless($servicio->puedeVer($request->user(), $difusion), 403);

        return response()->json(['data' => $servicio->presentar($difusion, true)]);
    }

    public function vistaPrevia(Request $request, DifusionService $servicio): JsonResponse
    {
        $reglas = DifusionService::reglas();
        unset($reglas['titulo'], $reglas['mensaje'], $reglas['programada_para']);
        $datos = $request->validate($reglas);
        $filtros = DifusionService::filtros($datos);
        $servicio->autorizar($request->user(), $filtros);

        return response()->json(['data' => $servicio->vistaPrevia($filtros, (array) $datos['canales'])]);
    }

    public function store(Request $request, DifusionService $servicio): JsonResponse
    {
        $datos = $request->validate(DifusionService::reglas());
        $difusion = $servicio->crear($datos, $request->user());

        return response()->json(['data' => $servicio->presentar($difusion, true)], 201);
    }

    public function cancelar(Request $request, Difusion $difusion, DifusionService $servicio): JsonResponse
    {
        abort_unless($servicio->puedeVer($request->user(), $difusion), 403);
        $servicio->cancelar($difusion);

        return response()->json(['data' => $servicio->presentar($difusion->fresh() ?? $difusion, true)]);
    }
}
