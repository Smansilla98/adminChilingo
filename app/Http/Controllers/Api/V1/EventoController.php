<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Agenda\EventoService;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\EventoResource;
use App\Models\Bloque;
use App\Models\Evento;
use App\Models\Profesor;
use App\Models\Sede;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Eventos: listado del alcance, detalle, alta, edición y baja. Mismas reglas que el
 * panel web (EventoService + EventoPolicy).
 */
class EventoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Evento::class);
        $query = Evento::query()->with(['sede:id,nombre', 'bloque:id,nombre', 'profesor:id,nombre']);
        $request->user()->acceso()->alcance('eventos.view')->aplicarEventos($query);

        // Por defecto, próximos (como antes); `historial=1` muestra los pasados primero.
        if ($request->boolean('historial')) {
            $query->where('fecha', '<', now()->toDateString())->orderByDesc('fecha');
        } else {
            $query->where('fecha', '>=', $request->date('desde')?->toDateString() ?? now()->toDateString())->orderBy('fecha')->orderBy('hora_inicio');
        }
        if ($request->filled('hasta')) {
            $query->where('fecha', '<=', $request->date('hasta')->toDateString());
        }
        foreach (['sede_id', 'profesor_id', 'bloque_id'] as $f) {
            if ($request->filled($f)) {
                $query->where($f, $request->integer($f));
            }
        }
        if ($request->filled('tipo_evento')) {
            $query->where('tipo_evento', $request->input('tipo_evento'));
        }
        if ($request->filled('q')) {
            $t = '%'.trim((string) $request->input('q')).'%';
            $query->where(fn ($w) => $w->where('titulo', 'like', $t)->orWhere('descripcion', 'like', $t));
        }
        $pagina = $query->paginate(30);

        return response()->json([
            'data' => collect($pagina->items())->map(fn (Evento $e) => $this->evento($request, $e))->values(),
            'meta' => ['current_page' => $pagina->currentPage(), 'last_page' => $pagina->lastPage(), 'total' => $pagina->total()],
        ]);
    }

    public function show(Request $request, Evento $evento): JsonResponse
    {
        $this->authorize('view', $evento);

        return response()->json(['data' => $this->evento($request, $evento->load(['sede', 'bloque', 'profesor', 'creador']), true)]);
    }

    public function store(Request $request, EventoService $eventos): JsonResponse
    {
        $this->authorize('create', Evento::class);
        $evento = $eventos->crear($request->validate($eventos->reglas()), $request->user());

        return response()->json(['data' => $this->evento($request, $evento->load(['sede', 'bloque', 'profesor', 'creador']), true)], 201);
    }

    public function update(Request $request, Evento $evento, EventoService $eventos): JsonResponse
    {
        $this->authorize('update', $evento);
        $eventos->actualizar($evento, $request->validate($eventos->reglas()), $request->user());

        return response()->json(['data' => $this->evento($request, $evento->fresh(['sede', 'bloque', 'profesor', 'creador']), true)]);
    }

    public function destroy(Evento $evento): JsonResponse
    {
        $this->authorize('delete', $evento);
        $evento->delete();

        return response()->json(['ok' => true]);
    }

    public function catalogo(): JsonResponse
    {
        return response()->json([
            'tipos' => EventoService::TIPOS,
            'sedes' => Sede::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'profesores' => Profesor::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'bloques' => Bloque::query()->where('activo', true)->with('sede:id,nombre')->orderBy('nombre')->get()
                ->map(fn (Bloque $b) => ['id' => $b->id, 'nombre' => $b->nombre, 'sede_id' => $b->sede_id, 'sede' => $b->sede?->nombre])->values(),
        ]);
    }

    /** @return array<string, mixed> */
    private function evento(Request $request, Evento $e, bool $detalle = false): array
    {
        $user = $request->user();

        return (new EventoResource($e))->toArray($request) + [
            'tipo_nombre' => EventoService::TIPOS[$e->tipo_evento] ?? $e->tipo_evento,
            'profesor' => $e->relationLoaded('profesor') && $e->profesor ? ['id' => $e->profesor->id, 'nombre' => $e->profesor->nombre] : null,
            'cantidad_personas' => $e->cantidad_personas,
            'ambito' => $e->bloque_id ? 'bloque' : ($e->sede_id ? 'sede' : 'escuela'),
            'creado_por' => $detalle ? $e->creador?->name : null,
            'acciones' => $detalle ? [
                'editar' => $user->can('update', $e),
                'eliminar' => $user->can('delete', $e),
            ] : null,
        ];
    }
}
