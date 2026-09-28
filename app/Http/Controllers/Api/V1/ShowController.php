<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Agenda\ShowService;
use App\Http\Controllers\Controller;
use App\Models\Bloque;
use App\Models\Show;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shows y convocatorias. Mismas reglas que el panel web (ShowService + ShowPolicy).
 */
class ShowController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Show::class);
        $query = Show::query()->with('bloques:id,nombre,sede_id')->withCount('bloques');
        if ($request->boolean('proximos')) {
            $query->proximos();
        } else {
            $query->orderByDesc('fecha');
        }
        if ($request->filled('q')) {
            $t = '%'.trim((string) $request->input('q')).'%';
            $query->where(fn ($w) => $w->where('titulo', 'like', $t)->orWhere('lugar', 'like', $t));
        }
        $pagina = $query->paginate(30);

        return response()->json([
            'data' => collect($pagina->items())->map(fn (Show $s) => $this->presentar($request, $s))->values(),
            'meta' => ['current_page' => $pagina->currentPage(), 'last_page' => $pagina->lastPage(), 'total' => $pagina->total()],
        ]);
    }

    public function show(Request $request, Show $show): JsonResponse
    {
        $this->authorize('view', $show);

        return response()->json(['data' => $this->presentar($request, $show->load('bloques.sede', 'bloques.profesor'), true)]);
    }

    public function store(Request $request, ShowService $shows): JsonResponse
    {
        $this->authorize('create', Show::class);
        $datos = $request->validate($shows->reglas());
        $datos['convocatoria_abierta'] = $request->boolean('convocatoria_abierta');
        $show = $shows->guardar(null, $datos, $request->user());

        return response()->json(['data' => $this->presentar($request, $show->load('bloques.sede', 'bloques.profesor'), true)], 201);
    }

    public function update(Request $request, Show $show, ShowService $shows): JsonResponse
    {
        $this->authorize('update', $show);
        $datos = $request->validate($shows->reglas());
        $datos['convocatoria_abierta'] = $request->boolean('convocatoria_abierta');
        $shows->guardar($show, $datos, $request->user());

        return response()->json(['data' => $this->presentar($request, $show->fresh(['bloques.sede', 'bloques.profesor']), true)]);
    }

    public function destroy(Show $show, ShowService $shows): JsonResponse
    {
        $this->authorize('delete', $show);
        $shows->eliminar($show);

        return response()->json(['ok' => true]);
    }

    /** @return array<string, mixed> */
    private function presentar(Request $request, Show $s, bool $detalle = false): array
    {
        $user = $request->user();

        return [
            'id' => $s->id,
            'titulo' => $s->titulo,
            'fecha' => $s->fecha?->toDateString(),
            'hora_inicio' => $s->hora_inicio?->format('H:i'),
            'hora_fin' => $s->hora_fin?->format('H:i'),
            'lugar' => $s->lugar,
            'descripcion' => $s->descripcion,
            'convocatoria_abierta' => (bool) $s->convocatoria_abierta,
            'bloques' => $s->bloques->map(fn (Bloque $b) => [
                'id' => $b->id,
                'nombre' => $b->nombre,
                'sede' => $detalle ? $b->sede?->nombre : null,
                'profesor' => $detalle ? $b->profesor?->nombre : null,
            ])->values(),
            'acciones' => $detalle ? ['editar' => $user->can('update', $s), 'eliminar' => $user->can('delete', $s)] : null,
        ];
    }
}
