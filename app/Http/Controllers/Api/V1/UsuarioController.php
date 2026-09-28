<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Acceso\CatalogoPermisos;
use App\Domain\Acceso\PresentadorAcceso;
use App\Domain\Acceso\UsuarioService;
use App\Http\Controllers\Controller;
use App\Models\Bloque;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

/**
 * Administración › Usuarios: cuentas, estado, contraseña y, junto con
 * AccesosController, roles y permisos con alcance. Mismas reglas que el panel web
 * (UsuarioService + UserPolicy). La administración de cuentas es global.
 */
class UsuarioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);
        $pagina = User::query()
            ->with(['persona:id,nombre,apellido,dni', 'persona.asignaciones' => fn ($q) => $q->vigentes()->with(['role:id,name', 'sede:id,nombre', 'bloque:id,nombre'])])
            ->when($request->filled('q'), function ($q) use ($request) {
                $t = '%'.trim((string) $request->input('q')).'%';
                $q->where(fn ($w) => $w->where('name', 'like', $t)->orWhere('username', 'like', $t)->orWhere('email', 'like', $t)
                    ->orWhereHas('persona', fn ($p) => $p->where('nombre', 'like', $t)->orWhere('apellido', 'like', $t)->orWhere('dni', 'like', $t)));
            })
            ->when($request->input('estado') === 'inactivos', fn ($q) => $q->where('activo', false))
            ->when($request->input('estado') === 'activos', fn ($q) => $q->where('activo', true))
            ->orderBy('name')
            ->paginate(30);

        return response()->json([
            'data' => collect($pagina->items())->map(fn (User $u) => [
                'id' => $u->id,
                'username' => $u->username,
                'email' => $u->email,
                'nombre' => $u->persona?->nombre_completo ?? $u->name,
                'activo' => (bool) $u->activo,
                'ultimo_acceso' => $u->ultimo_acceso_at?->toIso8601String(),
                'roles' => $u->persona?->asignaciones->map(fn ($a) => $a->role ? CatalogoPermisos::nombreRol($a->role->name) : null)->filter()->unique()->values() ?? [],
            ])->values(),
            'meta' => ['current_page' => $pagina->currentPage(), 'last_page' => $pagina->lastPage(), 'total' => $pagina->total()],
        ]);
    }

    public function show(Request $request, User $usuario, PresentadorAcceso $presentador): JsonResponse
    {
        $this->authorize('view', $usuario);

        return response()->json(['data' => $this->ficha($request, $usuario, $presentador)]);
    }

    public function store(Request $request, UsuarioService $usuarios, PresentadorAcceso $presentador): JsonResponse
    {
        $this->authorize('create', User::class);
        $user = $usuarios->crear($request->validate($usuarios->reglasAlta(), $usuarios->mensajesAlta()), $request->user());

        return response()->json(['data' => $this->ficha($request, $user->fresh(), $presentador)], 201);
    }

    public function update(Request $request, User $usuario, UsuarioService $usuarios, PresentadorAcceso $presentador): JsonResponse
    {
        $this->authorize('update', $usuario);
        $usuarios->actualizar($usuario, $request->validate($usuarios->reglasEdicion($usuario)));

        return response()->json(['data' => $this->ficha($request, $usuario->fresh(), $presentador)]);
    }

    /** Activar o desactivar (desactivar cierra sus sesiones y la app). */
    public function estado(Request $request, User $usuario, UsuarioService $usuarios, PresentadorAcceso $presentador): JsonResponse
    {
        $this->authorize('update', $usuario);
        $data = $request->validate(['activo' => 'required|boolean']);
        $usuarios->establecerActivo($usuario, (bool) $data['activo'], $request->user());

        return response()->json(['data' => $this->ficha($request, $usuario->fresh(), $presentador)]);
    }

    /** Reemplazar la contraseña (cierra sus sesiones y la app). */
    public function resetear(Request $request, User $usuario, UsuarioService $usuarios): JsonResponse
    {
        $this->authorize('update', $usuario);
        $data = $request->validate(['password' => ['required', 'confirmed', Password::min(8)]]);
        $usuarios->resetearContrasena($usuario, $data['password']);

        return response()->json(['ok' => true]);
    }

    /** Opciones para crear cuentas y asignar roles/permisos. */
    public function catalogo(Request $request, UsuarioService $usuarios): JsonResponse
    {
        abort_unless($request->user()->acceso()->puedeAlguno(['usuarios.view', 'usuarios.create', 'usuarios.permissions']), 403);

        return response()->json([
            'roles' => $usuarios->rolesAsignables($request->user()),
            'roles_ambitos' => collect(CatalogoPermisos::roles())->map(fn ($r) => $r['ambitos'])->all(),
            'permisos' => CatalogoPermisos::grupos(),
            'sedes' => Sede::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'bloques' => Bloque::query()->where('activo', true)->with('sede:id,nombre')->orderBy('nombre')->get()
                ->map(fn (Bloque $b) => ['id' => $b->id, 'nombre' => $b->nombre, 'sede' => $b->sede?->nombre])->values(),
        ]);
    }

    /** @return array<string, mixed> */
    private function ficha(Request $request, User $usuario, PresentadorAcceso $presentador): array
    {
        $usuario->loadMissing('persona');
        $acceso = $usuario->acceso();
        $quien = $request->user();

        return [
            'id' => $usuario->id,
            'username' => $usuario->username,
            'email' => $usuario->email,
            'telefono' => $usuario->telefono,
            'nombre' => $usuario->persona?->nombre_completo ?? $usuario->name,
            'activo' => (bool) $usuario->activo,
            'ultimo_acceso' => $usuario->ultimo_acceso_at?->toIso8601String(),
            'persona' => $usuario->persona ? ['id' => $usuario->persona->id, 'nombre' => $usuario->persona->nombre_completo] : null,
            'superadmin' => $acceso->esSuperadmin(),
            'funciones' => $presentador->funciones($acceso),
            'permisos' => $presentador->permisosAgrupados($acceso),
            // Sesiones de la app (un token por dispositivo). No se expone el token.
            'dispositivos' => $usuario->tokens()->orderByDesc('last_used_at')->get(['id', 'name', 'last_used_at', 'expires_at', 'created_at'])
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'nombre' => $t->name,
                    'ultimo_uso' => $t->last_used_at?->toIso8601String(),
                    'vence' => $t->expires_at?->toIso8601String(),
                    'desde' => $t->created_at?->toIso8601String(),
                ])->values(),
            'acciones' => [
                'editar' => $quien->can('update', $usuario),
                'activar' => $quien->can('update', $usuario) && ! $quien->is($usuario),
                'gestionar_permisos' => $quien->can('managePermissions', $usuario),
            ],
        ];
    }
}
