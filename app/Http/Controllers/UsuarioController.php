<?php

namespace App\Http\Controllers;

use App\Domain\Acceso\CatalogoPermisos;
use App\Domain\Acceso\GestionAsignaciones;
use App\Domain\Acceso\PresentadorAcceso;
use App\Domain\Acceso\UsuarioService;
use App\Domain\Personas\PersonaService;
use App\Models\Asignacion;
use App\Models\Bloque;
use App\Models\Persona;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Administración › Usuarios y permisos.
 * Persona → cuenta → roles con alcance → permisos adicionales → permisos efectivos.
 */
class UsuarioController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $usuarios = User::query()
            ->with(['persona.asignaciones' => fn ($q) => $q->vigentes()->with(['role:id,name', 'sede:id,nombre', 'bloque:id,nombre']), 'persona.profesor:id,persona_id', 'persona.alumnos:id,persona_id'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $t = '%'.trim((string) $request->input('q')).'%';
                $q->where(fn ($w) => $w->where('name', 'like', $t)->orWhere('username', 'like', $t)->orWhere('email', 'like', $t)
                    ->orWhereHas('persona', fn ($p) => $p->where('nombre', 'like', $t)->orWhere('apellido', 'like', $t)->orWhere('dni', 'like', $t)));
            })
            ->when($request->input('estado') === 'inactivos', fn ($q) => $q->where('activo', false))
            ->when($request->input('estado') === 'activos', fn ($q) => $q->where('activo', true))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('usuarios.index', compact('usuarios'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', User::class);
        $persona = $request->integer('persona_id') ? Persona::query()->with('user')->find($request->integer('persona_id')) : null;

        return view('usuarios.create', [
            'persona' => $persona,
            'roles' => $this->rolesAsignables($request->user()),
            'sedes' => Sede::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(Request $request, UsuarioService $usuarios): RedirectResponse
    {
        $this->authorize('create', User::class);
        $user = $usuarios->crear($request->validate($usuarios->reglasAlta(), $usuarios->mensajesAlta()), $request->user());

        return redirect()->route('usuarios.show', $user)->with('success', 'Cuenta creada. Revisá abajo qué puede hacer.');
    }

    public function show(Request $request, User $usuario, PresentadorAcceso $presentador): View
    {
        $this->authorize('view', $usuario);
        $usuario->load('persona');
        $acceso = $usuario->acceso();

        return view('usuarios.show', [
            'usuario' => $usuario,
            'funciones' => $presentador->funciones($acceso),
            'permisos' => $presentador->permisosAgrupados($acceso),
            'esSuperadmin' => $acceso->esSuperadmin(),
            'roles' => $this->rolesAsignables($request->user()),
            'gruposPermisos' => CatalogoPermisos::grupos(),
            'sedes' => Sede::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'bloques' => Bloque::query()->where('activo', true)->with('sede:id,nombre')->orderBy('nombre')->get(['id', 'nombre', 'sede_id']),
            'puedeGestionarPermisos' => $request->user()->can('managePermissions', $usuario),
            'puedeEditar' => $request->user()->can('update', $usuario),
        ]);
    }

    public function update(Request $request, User $usuario, UsuarioService $usuarios): RedirectResponse
    {
        $this->authorize('update', $usuario);
        $usuarios->actualizar($usuario, $request->validate($usuarios->reglasEdicion($usuario)));

        return back()->with('success', 'Datos de la cuenta actualizados.');
    }

    public function cambiarEstado(Request $request, User $usuario, UsuarioService $usuarios): RedirectResponse
    {
        $this->authorize('update', $usuario);
        if ($request->user()->is($usuario)) {
            return back()->with('error', 'No podés desactivar tu propia cuenta.');
        }
        $activo = ! $usuario->activo;
        $usuarios->establecerActivo($usuario, $activo, $request->user());

        return back()->with('success', $activo ? 'Cuenta activada.' : 'Cuenta desactivada. Se cerraron sus sesiones y la app.');
    }

    public function resetearAcceso(Request $request, User $usuario, UsuarioService $usuarios): RedirectResponse
    {
        $this->authorize('update', $usuario);
        $data = $request->validate(['password' => ['required', 'confirmed', Password::min(8)]]);
        $usuarios->resetearContrasena($usuario, $data['password']);

        return back()->with('success', 'Contraseña reemplazada. Se cerraron las sesiones abiertas y la app deberá volver a ingresar.');
    }

    public function asignar(Request $request, User $usuario, GestionAsignaciones $asignaciones): RedirectResponse
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
        $persona = $usuario->persona ?? app(PersonaService::class)->asegurarParaUsuario($usuario);
        $asignaciones->asignar($persona, $data, $request->user());

        return back()->with('success', 'Asignación agregada.');
    }

    public function quitarAsignacion(Request $request, User $usuario, Asignacion $asignacion, GestionAsignaciones $asignaciones): RedirectResponse
    {
        $this->authorize('managePermissions', $usuario);
        abort_unless((int) $asignacion->persona_id === (int) $usuario->persona_id, 404);
        $asignaciones->quitar($asignacion, $request->user());

        return back()->with('success', 'Asignación quitada.');
    }

    /** @return array<string, string> */
    private function rolesAsignables(User $por): array
    {
        return app(UsuarioService::class)->rolesAsignables($por);
    }
}
