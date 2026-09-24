<?php

namespace App\Http\Controllers;

use App\Domain\Acceso\CatalogoPermisos;
use App\Domain\Acceso\GestionAsignaciones;
use App\Domain\Acceso\PresentadorAcceso;
use App\Domain\Personas\PersonaService;
use App\Models\Asignacion;
use App\Models\Bloque;
use App\Models\Persona;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
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

    public function store(Request $request, PersonaService $personas, GestionAsignaciones $asignaciones): RedirectResponse
    {
        $this->authorize('create', User::class);
        $data = $request->validate([
            'persona_id' => ['nullable', 'exists:personas,id'],
            'nombre' => ['required_without:persona_id', 'nullable', 'string', 'max:255'],
            'apellido' => ['nullable', 'string', 'max:255'],
            'dni' => ['nullable', 'string', 'max:20'],
            'telefono' => ['nullable', 'string', 'max:40'],
            'username' => ['required', 'string', 'max:80', 'alpha_dash', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'rol' => ['nullable', 'string', Rule::in(array_keys(CatalogoPermisos::roles()))],
            'ambito' => ['nullable', Rule::in(['global', 'sede'])],
            'sede_id' => ['nullable', 'exists:sedes,id'],
        ], [
            'username.unique' => 'Ese nombre de usuario ya está en uso.',
            'email.unique' => 'Ese correo ya está registrado.',
            'nombre.required_without' => 'Elegí una persona existente o cargá el nombre de la nueva.',
        ]);

        $user = DB::transaction(function () use ($data, $request, $personas, $asignaciones) {
            if (! empty($data['persona_id'])) {
                $persona = Persona::query()->with('user')->findOrFail($data['persona_id']);
                if ($persona->user) {
                    throw ValidationException::withMessages(['persona_id' => 'Esta persona ya tiene cuenta ('.$persona->user->username.'). Editala en lugar de crear otra.']);
                }
            } else {
                $persona = $personas->crear([
                    'nombre' => $data['nombre'],
                    'apellido' => $data['apellido'] ?? null,
                    'dni' => $data['dni'] ?? null,
                    'telefono' => $data['telefono'] ?? null,
                    'email' => $data['email'],
                ]);
            }

            $user = User::query()->create([
                'persona_id' => $persona->id,
                'name' => $persona->nombre_completo,
                'username' => $data['username'],
                'email' => $data['email'],
                'telefono' => $persona->telefono,
                'password' => Hash::make($data['password']),
                'role' => 'usuario',
            ]);

            if (! empty($data['rol'])) {
                $asignaciones->asignar($persona, [
                    'tipo' => 'rol',
                    'nombre' => $data['rol'],
                    'ambito' => $data['ambito'] ?? 'global',
                    'sede_id' => isset($data['sede_id']) ? (int) $data['sede_id'] : null,
                ], $request->user());
            }

            return $user;
        });

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

    public function update(Request $request, User $usuario): RedirectResponse
    {
        $this->authorize('update', $usuario);
        $data = $request->validate([
            'username' => ['required', 'string', 'max:80', 'alpha_dash', Rule::unique('users', 'username')->ignore($usuario->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario->id)],
            'telefono' => ['nullable', 'string', 'max:40'],
        ]);
        $usuario->forceFill($data)->save();

        return back()->with('success', 'Datos de la cuenta actualizados.');
    }

    public function cambiarEstado(Request $request, User $usuario): RedirectResponse
    {
        $this->authorize('update', $usuario);
        if ($request->user()->is($usuario)) {
            return back()->with('error', 'No podés desactivar tu propia cuenta.');
        }
        $activo = ! $usuario->activo;
        $usuario->forceFill(['activo' => $activo])->save();
        if (! $activo) {
            $this->cerrarSesiones($usuario);
        }

        return back()->with('success', $activo ? 'Cuenta activada.' : 'Cuenta desactivada. Se cerraron sus sesiones y la app.');
    }

    public function resetearAcceso(Request $request, User $usuario): RedirectResponse
    {
        $this->authorize('update', $usuario);
        $data = $request->validate(['password' => ['required', 'confirmed', Password::min(8)]]);
        $usuario->forceFill(['password' => Hash::make($data['password'])])->save();
        $this->cerrarSesiones($usuario);

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
        $out = [];
        foreach (CatalogoPermisos::roles() as $clave => $def) {
            if (($def['derivado'] ?? false)) {
                continue;
            }
            if (CatalogoPermisos::esRolDeAdministracion($clave) && ! $por->acceso()->puedeGlobal('usuarios.assign_admin')) {
                continue;
            }
            $out[$clave] = $def['nombre'].' · '.implode(' / ', $def['ambitos']);
        }

        return $out;
    }

    private function cerrarSesiones(User $usuario): void
    {
        $usuario->tokens()->delete();
        DB::table('sessions')->where('user_id', $usuario->id)->delete();
    }
}
