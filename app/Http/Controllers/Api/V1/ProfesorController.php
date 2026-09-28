<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Datos\EliminacionSegura;
use App\Domain\Personas\ProfesorService;
use App\Http\Controllers\Controller;
use App\Models\Bloque;
use App\Models\Profesor;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Plantel docente. Mismas reglas que el panel web (ProfesorService, ProfesorPolicy,
 * EliminacionSegura). Bloques y sedes llegan como listas: bloques[{bloque_id, rol}],
 * sedes[{sede_id, rol}].
 */
class ProfesorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Profesor::class);
        $query = Profesor::query()->with(['bloques:id,nombre,sede_id', 'sedesConRol:id,nombre', 'user:id,username'])
            ->withCount('bloques')->orderBy('nombre');
        $alcance = $request->user()->acceso()->alcance('profesores.view');
        if (! $alcance->esGlobal()) {
            $query->where(fn ($q) => $q->whereHas('bloques', fn ($b) => $alcance->aplicarBloques($b))
                ->orWhereHas('sedesConRol', fn ($ps) => $ps->whereIn('sedes.id', $alcance->sedesTocadas() ?: [0])));
        }
        if ($request->filled('q')) {
            $t = '%'.trim((string) $request->input('q')).'%';
            $query->where(fn ($w) => $w->where('nombre', 'like', $t)->orWhere('email', 'like', $t)->orWhere('telefono', 'like', $t));
        }
        if ($request->filled('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }
        if ($request->filled('sede_id')) {
            $sede = $request->integer('sede_id');
            $query->where(fn ($q) => $q->whereHas('bloques', fn ($b) => $b->where('sede_id', $sede))
                ->orWhereHas('sedesConRol', fn ($s) => $s->where('sedes.id', $sede)));
        }
        $pagina = $query->paginate(30);

        return response()->json([
            'data' => collect($pagina->items())->map(fn (Profesor $p) => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'telefono' => $p->telefono,
                'email' => $p->email,
                'activo' => (bool) $p->activo,
                'persona_id' => $p->persona_id,
                'cantidad_bloques' => $p->bloques_count,
                'bloques' => $p->bloques->pluck('nombre')->values(),
                'sedes' => $p->sedesConRol->map(fn ($s) => $s->nombre.' ('.(Profesor::ROLES_SEDE[$s->pivot->rol] ?? $s->pivot->rol).')')->values(),
                'usuario' => $p->user?->username,
            ])->values(),
            'meta' => ['current_page' => $pagina->currentPage(), 'last_page' => $pagina->lastPage(), 'total' => $pagina->total()],
        ]);
    }

    public function show(Request $request, Profesor $profesor): JsonResponse
    {
        $this->authorize('view', $profesor);

        return response()->json(['data' => $this->ficha($request, $profesor)]);
    }

    public function store(Request $request, ProfesorService $servicio): JsonResponse
    {
        $this->authorize('create', Profesor::class);
        $modo = (string) $request->input('cuenta_modo', 'ninguna');
        $datos = $request->validate($servicio->reglas($modo) + $this->reglasAsignaciones() + ['persona_id' => ['nullable', 'exists:personas,id']]);
        $datos['activo'] = $request->boolean('activo', true);
        $profesor = $servicio->crear($datos, $this->bloques($datos), $this->sedes($datos));

        return response()->json(['data' => $this->ficha($request, $profesor->fresh())], 201);
    }

    public function update(Request $request, Profesor $profesor, ProfesorService $servicio): JsonResponse
    {
        $this->authorize('update', $profesor);
        $modo = (string) $request->input('cuenta_modo', $profesor->user_id ? 'existente' : 'ninguna');
        $datos = $request->validate($servicio->reglas($modo, $profesor->id) + $this->reglasAsignaciones());
        $datos['activo'] = $request->boolean('activo', (bool) $profesor->activo);
        $datos['cuenta_modo'] = $modo;
        $servicio->actualizar(
            $profesor,
            $datos,
            array_key_exists('bloques', $datos) ? $this->bloques($datos) : $profesor->bloques->map(fn ($b) => ['bloque_id' => $b->id, 'rol' => $b->pivot->rol])->all(),
            array_key_exists('sedes', $datos) ? $this->sedes($datos) : $profesor->sedesConRol->map(fn ($s) => ['sede_id' => $s->id, 'rol' => $s->pivot->rol])->all(),
        );

        return response()->json(['data' => $this->ficha($request, $profesor->fresh())]);
    }

    public function destroy(Profesor $profesor, EliminacionSegura $eliminacion): JsonResponse
    {
        $this->authorize('delete', $profesor);
        $eliminacion->verificar($profesor);
        $profesor->delete();

        return response()->json(['ok' => true]);
    }

    /** Opciones para el formulario: bloques y sedes activos, roles. */
    public function catalogo(Request $request): JsonResponse
    {
        abort_unless($request->user()->acceso()->puedeAlguno(['profesores.create', 'profesores.update']), 403);

        return response()->json([
            'roles_bloque' => array_combine(Profesor::ROLES_BLOQUE, array_map(fn ($r) => ucfirst(str_replace('_', ' ', $r)), Profesor::ROLES_BLOQUE)),
            'roles_sede' => Profesor::ROLES_SEDE,
            'bloques' => Bloque::query()->with('sede:id,nombre')->where('activo', true)->orderBy('nombre')->get()
                ->map(fn (Bloque $b) => ['id' => $b->id, 'nombre' => $b->nombre, 'sede' => $b->sede?->nombre])->values(),
            'sedes' => Sede::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'usa_username' => app(ProfesorService::class)->hayUsername(),
        ]);
    }

    /** Cuentas de acceso sin ficha docente (para vincular una existente). */
    public function usuariosDisponibles(Request $request): JsonResponse
    {
        abort_unless($request->user()->acceso()->puedeAlguno(['profesores.create', 'profesores.update']), 403);
        $ocupados = Profesor::query()->whereNotNull('user_id')
            ->when($request->filled('excepto'), fn ($q) => $q->where('id', '!=', $request->integer('excepto')))
            ->pluck('user_id');
        $q = User::query()->whereNotIn('id', $ocupados)->orderBy('name');
        if ($request->filled('q')) {
            $t = '%'.trim((string) $request->input('q')).'%';
            $q->where(fn ($w) => $w->where('name', 'like', $t)->orWhere('username', 'like', $t)->orWhere('email', 'like', $t));
        }

        return response()->json(['data' => $q->limit(30)->get(['id', 'name', 'username', 'email'])]);
    }

    /** @return array<string, mixed> */
    private function reglasAsignaciones(): array
    {
        return [
            'bloques' => ['sometimes', 'array'],
            'bloques.*.bloque_id' => ['required', 'integer', 'exists:bloques,id'],
            'bloques.*.rol' => ['required', Rule::in(Profesor::ROLES_BLOQUE)],
            'sedes' => ['sometimes', 'array'],
            'sedes.*.sede_id' => ['required', 'integer', 'exists:sedes,id'],
            'sedes.*.rol' => ['required', Rule::in(array_keys(Profesor::ROLES_SEDE))],
        ];
    }

    /** @return array<int, array{bloque_id: int, rol: string}> */
    private function bloques(array $datos): array
    {
        return array_map(fn ($b) => ['bloque_id' => (int) $b['bloque_id'], 'rol' => (string) $b['rol']], $datos['bloques'] ?? []);
    }

    /** @return array<int, array{sede_id: int, rol: string}> */
    private function sedes(array $datos): array
    {
        return array_map(fn ($s) => ['sede_id' => (int) $s['sede_id'], 'rol' => (string) $s['rol']], $datos['sedes'] ?? []);
    }

    /** @return array<string, mixed> */
    private function ficha(Request $request, Profesor $profesor): array
    {
        $profesor->loadMissing(['bloques.sede', 'sedesConRol', 'user', 'coordinadorAreas', 'eventos' => fn ($q) => $q->where('fecha', '>=', now()->toDateString())->orderBy('fecha')->limit(10)]);
        $alumno = $profesor->alumnoPerfil();
        $user = $request->user();

        return [
            'id' => $profesor->id,
            'nombre' => $profesor->nombre,
            'telefono' => $profesor->telefono,
            'email' => $profesor->email,
            'activo' => (bool) $profesor->activo,
            'persona_id' => $profesor->persona_id,
            'cuenta' => $profesor->user ? ['id' => $profesor->user->id, 'username' => $profesor->user->username, 'activo' => (bool) $profesor->user->activo] : null,
            'bloques' => $profesor->bloques->map(fn (Bloque $b) => [
                'id' => $b->id,
                'nombre' => $b->nombre,
                'sede' => $b->sede?->nombre,
                'rol' => $b->pivot->rol,
                'cantidad_alumnos' => $b->cantidad_alumnos,
            ])->values(),
            'sedes' => $profesor->sedesConRol->map(fn ($s) => ['id' => $s->id, 'nombre' => $s->nombre, 'rol' => $s->pivot->rol, 'rol_nombre' => Profesor::ROLES_SEDE[$s->pivot->rol] ?? $s->pivot->rol])->values(),
            'areas' => $profesor->coordinadorAreas->pluck('area')->values(),
            'eventos' => $profesor->eventos->map(fn ($e) => ['id' => $e->id, 'titulo' => $e->titulo, 'fecha' => $e->fecha?->toDateString()])->values(),
            'alumno_id' => $alumno?->id,
            'acciones' => [
                'editar' => $user->can('update', $profesor),
                'eliminar' => $user->can('delete', $profesor),
                'ver_persona' => $profesor->persona_id !== null,
            ],
        ];
    }
}
