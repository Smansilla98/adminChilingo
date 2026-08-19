<?php

namespace App\Http\Controllers;

use App\Models\Bloque;
use App\Models\Profesor;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class ProfesorController extends Controller
{
    public function index()
    {
        try {
            $profesores = Profesor::withCount('bloques')->orderBy('nombre')->paginate(20);
        } catch (QueryException $e) {
            $profesores = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
        }

        return view('profesores.index', compact('profesores'));
    }

    public function create()
    {
        $bloquesParaAsignar = Bloque::with('sede')->where('activo', true)->orderBy('nombre')->get();
        $sedes = Sede::where('activo', true)->orderBy('nombre')->get();
        $usuarios = $this->usuariosParaVincular();
        $hasUsername = $this->hasUsernameColumn();

        return view('profesores.create', compact('bloquesParaAsignar', 'sedes', 'usuarios', 'hasUsername'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->reglasFicha($request));
        $validated['activo'] = $request->boolean('activo');
        unset($validated['cuenta_modo'], $validated['login_username'], $validated['login_password'], $validated['login_password_confirmation']);

        $modo = (string) $request->input('cuenta_modo', 'ninguna');
        if ($modo === 'nueva') {
            $validated['user_id'] = $this->crearUsuarioParaProfesor($request, $validated)->id;
        } elseif ($modo === 'existente') {
            $validated['user_id'] = $validated['user_id'] ?? null;
        } else {
            $validated['user_id'] = null;
        }

        foreach (['email', 'telefono'] as $col) {
            if (! Schema::hasColumn('profesores', $col)) {
                unset($validated[$col]);
            }
        }

        $profesor = Profesor::create($validated);
        $profesor->sincronizarAsignacionesBloques($this->filasAsignacionesBloquesDesdeRequest($request));
        $profesor->sincronizarRolesSede($this->filasSedeRolesDesdeRequest($request));
        $profesor->sincronizarRolesUsuario();

        return redirect()->route('profesores.index')
            ->with('success', 'Profesor creado exitosamente.');
    }

    public function show(Profesor $profesor)
    {
        $profesor->load(['bloques.sede', 'sedesConRol', 'eventos', 'user', 'coordinadorAreas']);
        $alumnoPerfil = $profesor->alumnoPerfil();

        return view('profesores.show', compact('profesor', 'alumnoPerfil'));
    }

    public function edit(Profesor $profesor)
    {
        $profesor->load(['bloques', 'sedesConRol']);
        $bloquesParaAsignar = Bloque::with('sede')->where('activo', true)->orderBy('nombre')->get();
        $sedes = Sede::where('activo', true)->orderBy('nombre')->get();
        $usuarios = $this->usuariosParaVincular($profesor->user_id);
        $hasUsername = $this->hasUsernameColumn();

        return view('profesores.edit', compact('profesor', 'bloquesParaAsignar', 'sedes', 'usuarios', 'hasUsername'));
    }

    public function update(Request $request, Profesor $profesor)
    {
        $validated = $request->validate($this->reglasFicha($request, $profesor->id));
        $validated['activo'] = $request->boolean('activo');
        unset($validated['cuenta_modo'], $validated['login_username'], $validated['login_password'], $validated['login_password_confirmation']);

        $modo = (string) $request->input('cuenta_modo', $profesor->user_id ? 'existente' : 'ninguna');
        if ($modo === 'nueva') {
            $validated['user_id'] = $this->crearUsuarioParaProfesor($request, $validated)->id;
        } elseif ($modo === 'ninguna') {
            $validated['user_id'] = null;
        } else {
            $validated['user_id'] = $validated['user_id'] ?? $profesor->user_id;
            if ($profesor->user && filled($request->input('login_password'))) {
                $profesor->user->forceFill([
                    'password' => Hash::make($request->input('login_password')),
                ])->save();
            }
        }

        foreach (['email', 'telefono'] as $col) {
            if (! Schema::hasColumn('profesores', $col)) {
                unset($validated[$col]);
            }
        }

        $profesor->update($validated);
        $profesor->sincronizarAsignacionesBloques($this->filasAsignacionesBloquesDesdeRequest($request));
        $profesor->sincronizarRolesSede($this->filasSedeRolesDesdeRequest($request));
        $profesor->sincronizarRolesUsuario();

        return redirect()->route('profesores.show', $profesor)
            ->with('success', 'Profesor actualizado exitosamente.');
    }

    public function destroy(Profesor $profesor)
    {
        $profesor->delete();

        return redirect()->route('profesores.index')
            ->with('success', 'Profesor eliminado exitosamente.');
    }

    /**
     * @return array<int, array{bloque_id: int, rol: string}>
     */
    private function filasAsignacionesBloquesDesdeRequest(Request $request): array
    {
        $filas = [];
        foreach ($request->input('asignaciones', []) as $key => $row) {
            if (! is_array($row)) {
                continue;
            }
            if (empty($row['asignado'])) {
                continue;
            }
            $bid = (int) ($row['bloque_id'] ?? $key);
            if ($bid <= 0) {
                continue;
            }
            $rol = $row['rol'] ?? 'ayudante';
            if (! in_array($rol, Profesor::ROLES_BLOQUE, true)) {
                $rol = 'ayudante';
            }
            $filas[] = ['bloque_id' => $bid, 'rol' => $rol];
        }

        return $filas;
    }

    /**
     * @return array<int, array{sede_id: int, rol: string}>
     */
    private function filasSedeRolesDesdeRequest(Request $request): array
    {
        if (! Schema::hasTable('profesor_sede')) {
            return [];
        }

        $filas = [];
        foreach ($request->input('sede_roles', []) as $sedeId => $roles) {
            if (! is_array($roles)) {
                continue;
            }
            $sid = (int) $sedeId;
            if ($sid <= 0) {
                continue;
            }
            foreach ($roles as $rol => $on) {
                if (! $on) {
                    continue;
                }
                if (! array_key_exists($rol, Profesor::ROLES_SEDE)) {
                    continue;
                }
                $filas[] = ['sede_id' => $sid, 'rol' => (string) $rol];
            }
        }

        return $filas;
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\User>
     */
    private function usuariosParaVincular(?int $keepUserId = null)
    {
        $ocupados = Profesor::query()
            ->when($keepUserId, fn ($q) => $q->where('user_id', '!=', $keepUserId))
            ->whereNotNull('user_id')
            ->pluck('user_id');

        return \App\Models\User::query()
            ->whereNotIn('id', $ocupados)
            ->orderBy('name')
            ->orderBy('username')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function reglasFicha(Request $request, ?int $profesorId = null): array
    {
        $modo = (string) $request->input('cuenta_modo', 'ninguna');
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
            'cuenta_modo' => ['required', Rule::in(['ninguna', 'existente', 'nueva'])],
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
            if ($this->hasUsernameColumn()) {
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
     * @param  array{nombre: string, email?: ?string, telefono?: ?string}  $ficha
     */
    private function crearUsuarioParaProfesor(Request $request, array $ficha): User
    {
        $payload = [
            'name' => $ficha['nombre'],
            'email' => $ficha['email'] ?? $request->input('email'),
            'password' => Hash::make((string) $request->input('login_password')),
            'role' => 'profesor',
        ];
        if ($this->hasUsernameColumn()) {
            $payload['username'] = $request->input('login_username');
        }
        if (Schema::hasColumn('users', 'telefono') && ! empty($ficha['telefono'])) {
            $payload['telefono'] = $ficha['telefono'];
        }

        $user = User::query()->create($payload);
        Role::firstOrCreate(['name' => 'profesor', 'guard_name' => 'web']);
        $user->syncRoles(['profesor']);

        return $user;
    }

    private function hasUsernameColumn(): bool
    {
        try {
            return Schema::hasColumn('users', 'username');
        } catch (\Throwable) {
            return false;
        }
    }
}
