<?php

namespace App\Domain\Acceso;

use App\Domain\Personas\PersonaService;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Cuentas de acceso (web y API): una persona tiene como máximo una cuenta. Desactivar
 * una cuenta o reemplazar su contraseña cierra sus sesiones web y los tokens de la app.
 */
class UsuarioService
{
    public function __construct(private PersonaService $personas, private GestionAsignaciones $asignaciones) {}

    /** @return array<string, mixed> */
    public function reglasAlta(): array
    {
        return [
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
        ];
    }

    /** @return array<string, string> */
    public function mensajesAlta(): array
    {
        return [
            'username.unique' => 'Ese nombre de usuario ya está en uso.',
            'email.unique' => 'Ese correo ya está registrado.',
            'nombre.required_without' => 'Elegí una persona existente o cargá el nombre de la nueva.',
        ];
    }

    /** @return array<string, mixed> */
    public function reglasEdicion(User $usuario): array
    {
        return [
            'username' => ['required', 'string', 'max:80', 'alpha_dash', Rule::unique('users', 'username')->ignore($usuario->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario->id)],
            'telefono' => ['nullable', 'string', 'max:40'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data  validado con reglasAlta()
     */
    public function crear(array $data, User $por): User
    {
        return DB::transaction(function () use ($data, $por) {
            if (! empty($data['persona_id'])) {
                $persona = Persona::query()->with('user')->findOrFail($data['persona_id']);
                if ($persona->user) {
                    throw ValidationException::withMessages(['persona_id' => 'Esta persona ya tiene cuenta ('.$persona->user->username.'). Editala en lugar de crear otra.']);
                }
            } else {
                $persona = $this->personas->crear([
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
                $this->asignaciones->asignar($persona, [
                    'tipo' => 'rol',
                    'nombre' => $data['rol'],
                    'ambito' => $data['ambito'] ?? 'global',
                    'sede_id' => isset($data['sede_id']) ? (int) $data['sede_id'] : null,
                ], $por);
            }

            return $user;
        });
    }

    /**
     * @param  array<string, mixed>  $data  validado con reglasEdicion()
     */
    public function actualizar(User $usuario, array $data): User
    {
        $usuario->forceFill($data)->save();

        return $usuario;
    }

    /** Activa o desactiva. Nadie se desactiva a sí mismo. */
    public function establecerActivo(User $usuario, bool $activo, User $por): User
    {
        if ($por->is($usuario) && ! $activo) {
            throw ValidationException::withMessages(['activo' => 'No podés desactivar tu propia cuenta.']);
        }
        $usuario->forceFill(['activo' => $activo])->save();
        if (! $activo) {
            $this->cerrarSesiones($usuario);
        }

        return $usuario;
    }

    public function resetearContrasena(User $usuario, string $password): void
    {
        $usuario->forceFill(['password' => Hash::make($password)])->save();
        $this->cerrarSesiones($usuario);
    }

    /** @return array<string, string> roles que `$por` puede asignar */
    public function rolesAsignables(User $por): array
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

    public function cerrarSesiones(User $usuario): void
    {
        $usuario->tokens()->delete();
        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $usuario->id)->delete();
        }
    }
}
