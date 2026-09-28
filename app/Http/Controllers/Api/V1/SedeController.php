<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Agenda\SedeService;
use App\Domain\Datos\EliminacionSegura;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\SedeResource;
use App\Models\Bloque;
use App\Models\Evento;
use App\Models\Sede;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sedes. Sin parámetros: sedes activas donde la persona tiene alguna función (catálogo
 * para formularios). Con `gestion=1`: listado administrativo (incluye inactivas) según
 * el alcance de `sedes.view`, como el panel web.
 */
class SedeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $acceso = $request->user()->acceso();
        if ($request->boolean('gestion')) {
            $this->authorize('viewAny', Sede::class);
            $query = Sede::query()->withCount(['bloques', 'alumnos'])->orderBy('nombre');
            $acceso->alcance('sedes.view')->aplicarPorSede($query, 'id');
            if ($request->filled('activo')) {
                $query->where('activo', $request->boolean('activo'));
            }
            if ($request->filled('q')) {
                $t = '%'.trim((string) $request->input('q')).'%';
                $query->where(fn ($w) => $w->where('nombre', 'like', $t)->orWhere('direccion', 'like', $t));
            }
            $pagina = $query->paginate(50);

            return response()->json([
                'data' => collect($pagina->items())->map(fn (Sede $s) => (new SedeResource($s))->toArray($request) + [
                    'cantidad_bloques' => $s->bloques_count,
                    'cantidad_alumnos' => $s->alumnos_count,
                    'tipo_propiedad' => $s->tipo_propiedad,
                ])->values(),
                'meta' => ['current_page' => $pagina->currentPage(), 'last_page' => $pagina->lastPage(), 'total' => $pagina->total()],
            ]);
        }

        $query = Sede::query()->where('activo', true)->orderBy('nombre');
        if (! $acceso->puedeGlobal('sedes.view')) {
            $ids = [];
            foreach ($acceso->roles() as $r) {
                if ($r->sedeId) {
                    $ids[] = $r->sedeId;
                }
            }
            $query->whereIn('id', array_values(array_unique($ids)) ?: [0]);
        }

        return SedeResource::collection($query->get());
    }

    public function show(Request $request, Sede $sede): JsonResponse
    {
        $this->authorize('view', $sede);

        return response()->json(['data' => $this->ficha($request, $sede)]);
    }

    public function store(Request $request, SedeService $sedes): JsonResponse
    {
        $this->authorize('create', Sede::class);
        $datos = $request->validate($sedes->reglas());
        $datos['activo'] = $request->boolean('activo', true);

        return response()->json(['data' => $this->ficha($request, $sedes->crear($datos))], 201);
    }

    public function update(Request $request, Sede $sede, SedeService $sedes): JsonResponse
    {
        $this->authorize('update', $sede);
        $datos = $request->validate($sedes->reglas($sede));
        $datos['activo'] = $request->boolean('activo', (bool) $sede->activo);
        // Edición parcial: lo que no se envía conserva su valor (no se aplican los valores por defecto).
        foreach (['direccion', 'tipo_propiedad', 'costo_alquiler_mensual', 'liquidacion_retencion_escuela', 'liquidacion_porc_docente'] as $campo) {
            if (! $request->exists($campo)) {
                $datos[$campo] = $sede->{$campo};
            }
        }

        return response()->json(['data' => $this->ficha($request, $sedes->actualizar($sede, $datos)->fresh())]);
    }

    public function destroy(Sede $sede, EliminacionSegura $eliminacion): JsonResponse
    {
        $this->authorize('delete', $sede);
        $eliminacion->verificar($sede);
        $sede->delete();

        return response()->json(['ok' => true]);
    }

    public function catalogo(): JsonResponse
    {
        return response()->json(['tipos_propiedad' => SedeService::TIPOS_PROPIEDAD]);
    }

    /** @return array<string, mixed> */
    private function ficha(Request $request, Sede $sede): array
    {
        $user = $request->user();
        $bloques = Bloque::query()->where('sede_id', $sede->id)->with('profesor:id,nombre')->withCount('alumnos')->orderBy('nombre')->get();
        $eventos = Evento::query()->where('sede_id', $sede->id)->where('fecha', '>=', now()->toDateString())->orderBy('fecha')->limit(10)->get();
        $puedeFinanzas = $user->acceso()->puedeEnSede('sedes.manage', $sede->id);

        return (new SedeResource($sede))->toArray($request) + [
            'tipo_propiedad' => $sede->tipo_propiedad,
            'tipo_propiedad_nombre' => SedeService::TIPOS_PROPIEDAD[$sede->tipo_propiedad] ?? $sede->tipo_propiedad,
            'costo_alquiler_mensual' => $puedeFinanzas && $sede->costo_alquiler_mensual !== null ? (float) $sede->costo_alquiler_mensual : null,
            'liquidacion_retencion_escuela' => $puedeFinanzas ? (float) ($sede->liquidacion_retencion_escuela ?? 0) : null,
            'liquidacion_porc_docente' => $puedeFinanzas ? $sede->porcentajeLiquidacionDocente() : null,
            'cantidad_alumnos' => $sede->alumnos()->where('activo', true)->count(),
            'bloques' => $bloques->map(fn (Bloque $b) => [
                'id' => $b->id,
                'nombre' => $b->nombre,
                'anio' => (int) $b->año,
                'activo' => (bool) $b->activo,
                'profesor' => $b->profesor?->nombre,
                'cantidad_alumnos' => $b->alumnos_count,
            ])->values(),
            'eventos' => $eventos->map(fn (Evento $e) => ['id' => $e->id, 'titulo' => $e->titulo, 'fecha' => $e->fecha?->toDateString(), 'hora_inicio' => $e->hora_inicio?->format('H:i')])->values(),
            'acciones' => [
                'editar' => $user->can('update', $sede),
                'eliminar' => $user->can('delete', $sede),
            ],
        ];
    }
}
