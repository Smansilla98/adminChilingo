<?php

namespace App\Domain\Personas;

use App\Models\Persona;
use App\Models\Profesor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Alta y edición de fichas docentes (panel web y API): datos, cuenta de acceso,
 * bloques con rol y roles por sede. Una persona tiene una sola ficha docente y una
 * sola cuenta.
 */
class ProfesorService
{
    public const MODOS_CUENTA = ['ninguna', 'existente', 'nueva'];

    /**
     * Reglas de la ficha según el modo de cuenta elegido.
     *
     * @return array<string, mixed>
     */
    public function reglas(string $modo, ?int $profesorId = null): array
    {
        $emailUnique = Rule::unique('profesores', 'email');
        $userUnique = Rule::unique('profesores', 'user_id');
        if ($profesorId) {
            $emailUnique = $emailUnique->ignore($profesorId);
            $userUnique = $userUnique->ignore($profesorId);
        }

        $rules = [
            'nombre' => ['required', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email'],
            'activo' => ['boolean'],
            'cuenta_modo' => ['required', Rule::in(self::MODOS_CUENTA)],
            'user_id' => ['nullable', 'exists:users,id', $userUnique],
            'login_username' => ['nullable', 'string', 'max:80'],
            'login_password' => ['nullable', 'confirmed', Password::min(8)],
        ];
        if (Schema::hasColumn('profesores', 'email')) {
            $rules['email'][] = $emailUnique;
        }

        if ($modo === 'existente') {
            $rules['user_id'][] = 'required';
        }
        if ($modo === 'nueva') {
            $rules['login_password'] = ['required', 'confirmed', Password::min(8)];
            if ($this->hayUsername()) {
                $rules['login_username'] = ['required', 'string', 'max:80', 'unique:users,username'];
            }
            $rules['email'] = ['required', 'email', 'unique:users,email'];
            if (Schema::hasColumn('profesores', 'email')) {
                $rules['email'][] = $emailUnique;
            }
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $datos  validados con reglas()
     * @param  array<int, array{bloque_id: int, rol: string}>  $bloques
     * @param  array<int, array{sede_id: int, rol: string}>  $sedes
     */
    public function crear(array $datos, array $bloques, array $sedes): Profesor
    {
        $modo = (string) ($datos['cuenta_modo'] ?? 'ninguna');
        $persona = ! empty($datos['persona_id']) ? Persona::query()->with('user')->find($datos['persona_id']) : null;
        if ($persona) {
            if (Profesor::query()->where('persona_id', $persona->id)->exists()) {
                throw ValidationException::withMessages(['persona_id' => 'Esta persona ya tiene ficha docente.']);
            }
            if ($persona->user) {
                // Una persona, una cuenta: el docente usa la cuenta que ya tiene.
                if ($modo === 'nueva') {
                    throw ValidationException::withMessages(['cuenta_modo' => 'Esta persona ya tiene cuenta ('.$persona->user->username.').']);
                }
                $modo = 'existente';
                $datos['user_id'] = $persona->user->id;
            }
        }

        return DB::transaction(function () use ($datos, $modo, $bloques, $sedes) {
            $ficha = $this->ficha($datos);
            $ficha['user_id'] = match ($modo) {
                'nueva' => $this->crearUsuario($datos)->id,
                'existente' => $datos['user_id'] ?? null,
                default => null,
            };
            if (! empty($datos['persona_id'])) {
                $ficha['persona_id'] = (int) $datos['persona_id'];
            }

            $profesor = Profesor::create($ficha);
            $this->sincronizar($profesor, $bloques, $sedes);

            return $profesor;
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<int, array{bloque_id: int, rol: string}>  $bloques
     * @param  array<int, array{sede_id: int, rol: string}>  $sedes
     */
    public function actualizar(Profesor $profesor, array $datos, array $bloques, array $sedes): Profesor
    {
        $modo = (string) ($datos['cuenta_modo'] ?? ($profesor->user_id ? 'existente' : 'ninguna'));

        return DB::transaction(function () use ($profesor, $datos, $modo, $bloques, $sedes) {
            $ficha = $this->ficha($datos);
            if ($modo === 'nueva') {
                $ficha['user_id'] = $this->crearUsuario($datos + ['persona_id' => $profesor->persona_id])->id;
            } elseif ($modo === 'ninguna') {
                $ficha['user_id'] = null;
            } else {
                $ficha['user_id'] = $datos['user_id'] ?? $profesor->user_id;
                if ($profesor->user && filled($datos['login_password'] ?? null)) {
                    $profesor->user->forceFill(['password' => Hash::make($datos['login_password'])])->save();
                }
            }

            $profesor->update($ficha);
            $this->sincronizar($profesor, $bloques, $sedes);

            return $profesor;
        });
    }

    /**
     * @param  array<int, array{bloque_id: int, rol: string}>  $bloques
     * @param  array<int, array{sede_id: int, rol: string}>  $sedes
     */
    private function sincronizar(Profesor $profesor, array $bloques, array $sedes): void
    {
        $profesor->sincronizarAsignacionesBloques($bloques);
        $profesor->sincronizarRolesSede($sedes);
        $profesor->sincronizarRolesUsuario();
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function ficha(array $datos): array
    {
        $ficha = [
            'nombre' => $datos['nombre'],
            'telefono' => $datos['telefono'] ?? null,
            'email' => $datos['email'] ?? null,
            'activo' => (bool) ($datos['activo'] ?? false),
        ];
        foreach (['email', 'telefono'] as $col) {
            if (! Schema::hasColumn('profesores', $col)) {
                unset($ficha[$col]);
            }
        }

        return $ficha;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function crearUsuario(array $datos): User
    {
        $payload = [
            'name' => $datos['nombre'],
            'email' => $datos['email'] ?? null,
            'password' => Hash::make((string) ($datos['login_password'] ?? '')),
            'role' => 'profesor',
        ];
        if ($this->hayUsername()) {
            $payload['username'] = $datos['login_username'] ?? null;
        }
        if (Schema::hasColumn('users', 'telefono') && ! empty($datos['telefono'])) {
            $payload['telefono'] = $datos['telefono'];
        }
        if (! empty($datos['persona_id'])) {
            $payload['persona_id'] = (int) $datos['persona_id'];
        }
        $user = User::query()->create($payload);
        Role::firstOrCreate(['name' => 'profesor', 'guard_name' => 'web']);
        $user->syncRoles(['profesor']);

        return $user;
    }

    public function hayUsername(): bool
    {
        try {
            return Schema::hasColumn('users', 'username');
        } catch (\Throwable) {
            return false;
        }
    }
}
