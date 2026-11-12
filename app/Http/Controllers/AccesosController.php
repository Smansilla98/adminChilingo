<?php

namespace App\Http\Controllers;

use App\Models\Profesor;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Alta rápida de cuentas (formulario heredado). La matriz de visibilidad de módulos
 * (users.modulos_access) se retiró: qué ve cada uno lo deciden roles y permisos.
 */
class AccesosController extends Controller
{
    public const ROLES_ALTA = [
        'profesor' => 'Profesor',
        'admin' => 'Administración',
        'direccion' => 'Dirección',
        'alumno' => 'Alumno (login)',
    ];

    public function create(): View
    {
        $profesores = Profesor::query()->with('user')->orderBy('nombre')->get();
        $hasUsername = $this->hasUsernameColumn();

        return view('accesos.create', compact('profesores', 'hasUsername'));
    }

    public function store(Request $request): RedirectResponse
    {
        $hasUsername = $this->hasUsernameColumn();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'role' => ['required', Rule::in(array_keys(self::ROLES_ALTA))],
            'telefono' => ['nullable', 'string', 'max:20'],
            'profesor_vinculo' => ['required', Rule::in(['ninguno', 'nuevo', 'existente'])],
            'profesor_id' => ['nullable', 'required_if:profesor_vinculo,existente', 'exists:profesores,id'],
        ];
        $messages = [
            'name.required' => 'El nombre es obligatorio.',
            'email.required' => 'El correo es obligatorio.',
            'email.unique' => 'Ese correo ya está registrado.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'profesor_id.required_if' => 'Elegí el profesor al que se asocia esta cuenta.',
        ];
        if ($hasUsername) {
            $rules['username'] = ['required', 'string', 'max:80', 'unique:users,username'];
            $messages['username.unique'] = 'Ese nombre de usuario ya está en uso.';
        }

        $data = $request->validate($rules, $messages);
        // Crear cuentas de dirección equivale a asignar administración.
        if (in_array($data['role'], ['admin', 'direccion'], true) && ! $request->user()->acceso()->puedeGlobal('usuarios.assign_admin')) {
            throw ValidationException::withMessages(['role' => 'No tenés permiso para crear cuentas de administración.']);
        }

        $user = DB::transaction(function () use ($data, $hasUsername) {
            $payload = [
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => $data['role'],
            ];
            if ($hasUsername) {
                $payload['username'] = $data['username'];
            }
            if (Schema::hasColumn('users', 'telefono')) {
                $payload['telefono'] = $data['telefono'] ?? null;
            }

            $user = User::query()->create($payload);
            $this->asignarRolSpatie($user, $data['role']);

            if ($data['profesor_vinculo'] === 'nuevo') {
                $ficha = [
                    'user_id' => $user->id,
                    'nombre' => $data['name'],
                    'activo' => true,
                ];
                if (Schema::hasColumn('profesores', 'email')) {
                    $ficha['email'] = $data['email'];
                }
                if (Schema::hasColumn('profesores', 'telefono')) {
                    $ficha['telefono'] = $data['telefono'] ?? null;
                }
                $profesor = Profesor::query()->create($ficha);
                $profesor->sincronizarRolesUsuario();
            }

            if ($data['profesor_vinculo'] === 'existente') {
                $profesor = Profesor::query()->findOrFail((int) $data['profesor_id']);
                if ($profesor->user_id && (int) $profesor->user_id !== (int) $user->id) {
                    throw ValidationException::withMessages([
                        'profesor_id' => 'Ese profesor ya tiene una cuenta ('.$profesor->user?->email.').',
                    ]);
                }
                $profesor->forceFill(['user_id' => $user->id])->save();
                $profesor->sincronizarRolesUsuario();
            }

            return $user->fresh();
        });

        $aviso = 'Usuario creado.';
        if ($data['profesor_vinculo'] !== 'ninguno') {
            $aviso = 'Usuario creado y asociado al plantel docente.';
        }

        return redirect()
            ->route('usuarios.show', $user)
            ->with('success', $aviso);
    }

    private function hasUsernameColumn(): bool
    {
        try {
            return Schema::hasColumn('users', 'username');
        } catch (\Throwable) {
            return false;
        }
    }

    private function asignarRolSpatie(User $user, string $rol): void
    {
        Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        $user->syncRoles([$rol]);
    }
}
