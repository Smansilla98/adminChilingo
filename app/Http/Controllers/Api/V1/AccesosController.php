<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Acceso\CatalogoPermisos;
use App\Domain\Acceso\GestionAsignaciones;
use App\Domain\Acceso\PresentadorAcceso;
use App\Domain\Personas\PersonaService;
use App\Http\Controllers\Controller;
use App\Models\Asignacion;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Catálogo de roles/permisos y administración de accesos (usuarios.*).
 */
class AccesosController extends Controller
{
    public function catalogo(Request $request): JsonResponse
    {
        abort_unless($request->user()->acceso()->puedeAlguno(['usuarios.view', 'usuarios.permissions']), 403);

        return response()->json([
            'permisos' => CatalogoPermisos::grupos(),
            'roles' => collect(CatalogoPermisos::roles())->map(fn ($r, $k) => [
                'clave' => $k,
                'nombre' => $r['nombre'],
                'descripcion' => $r['descripcion'] ?? null,
                'ambitos' => $r['ambitos'],
                'derivado' => (bool) ($r['derivado'] ?? false),
                'permisos' => CatalogoPermisos::permisosDeRol($k),
            ])->values(),
        ]);
    }

    public function usuarios(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);
        $q = User::query()->with('persona:id,nombre,apellido')->orderBy('name');
        if ($request->filled('q')) {
            $t = '%'.trim((string) $request->input('q')).'%';
            $q->where(fn ($w) => $w->where('name', 'like', $t)->orWhere('username', 'like', $t)->orWhere('email', 'like', $t));
        }
        $pagina = $q->paginate(30);

        return response()->json([
            'data' => collect($pagina->items())->map(fn (User $u) => [
                'id' => $u->id,
                'username' => $u->username,
                'nombre' => $u->persona?->nombre_completo ?? $u->name,
                'activo' => (bool) $u->activo,
                'ultimo_acceso' => $u->ultimo_acceso_at?->toIso8601String(),
            ]),
            'meta' => ['current_page' => $pagina->currentPage(), 'last_page' => $pagina->lastPage(), 'total' => $pagina->total()],
        ]);
    }

    public function usuario(Request $request, User $usuario, PresentadorAcceso $presentador): JsonResponse
    {
        $this->authorize('view', $usuario);
        $acceso = $usuario->acceso();

        return response()->json([
            'id' => $usuario->id,
            'username' => $usuario->username,
            'activo' => (bool) $usuario->activo,
            'persona_id' => $usuario->persona_id,
            'funciones' => $presentador->funciones($acceso),
            'permisos' => $presentador->permisosAgrupados($acceso),
        ]);
    }

    public function asignar(Request $request, User $usuario, GestionAsignaciones $asignaciones, PersonaService $personas): JsonResponse
    {
        $this->authorize('managePermissions', $usuario);
        $data = $request->validate([
            'tipo' => ['required', Rule::in(['rol', 'permiso'])],
            'nombre' => ['required', 'string', 'max:80'],
            'ambito' => ['required', Rule::in(['global', 'sede', 'bloque'])],
            'sede_id' => ['nullable', 'exists:sedes,id'],
            'bloque_id' => ['nullable', 'exists:bloques,id'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'notas' => ['nullable', 'string', 'max:500'],
        ]);
        $persona = $usuario->persona ?? $personas->asegurarParaUsuario($usuario);
        $a = $asignaciones->asignar($persona, $data, $request->user());

        return response()->json(['id' => $a->id], 201);
    }

    public function quitar(Request $request, User $usuario, Asignacion $asignacion, GestionAsignaciones $asignaciones): JsonResponse
    {
        $this->authorize('managePermissions', $usuario);
        abort_unless((int) $asignacion->persona_id === (int) $usuario->persona_id, 404);
        $asignaciones->quitar($asignacion, $request->user());

        return response()->json(['ok' => true]);
    }
}
