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

class AccesosController extends Controller
{
    /**
     * Definición de módulos/submódulos para la matriz de accesos.
     * Clave => Etiqueta (grupo).
     *
     * Nota: esta matriz controla visibilidad + bloqueo por middleware "modulo".
     */
    private const MODULOS = [
        'programa' => ['Programa'],
        'calendario' => ['Calendario'],
        'ayuda' => ['Guía de uso'],
        'comprobantes' => ['Comprobantes'],

        // Profesor
        'profesor.mis_bloques' => ['Profesor', 'Mis bloques'],
        'profesor.asistencia' => ['Profesor', 'Asistencia'],
        'profesor.mis_alumnos' => ['Profesor', 'Mis alumnos'],
        'profesor.pagos_cuotas' => ['Profesor', 'Pagos de cuotas'],
        'profesor.mis_eventos' => ['Profesor', 'Mis eventos'],

        // Admin (módulos grandes)
        'admin.alumnos' => ['Administración', 'Alumnos'],
        'admin.importar' => ['Administración', 'Importar alumnos'],
        'admin.profesores' => ['Administración', 'Profesores'],
        'admin.bloques' => ['Administración', 'Bloques'],
        'admin.sedes' => ['Administración', 'Sedes'],
        'admin.cuotas' => ['Administración', 'Cuotas'],
        'admin.pagos' => ['Administración', 'Pagos'],
        'admin.eventos' => ['Administración', 'Eventos'],
        'admin.asistencias' => ['Administración', 'Asistencias'],
        'admin.reportes' => ['Administración', 'Reportes'],
        'admin.facturacion_mensual' => ['Administración', 'Facturación mensual'],
        'admin.inventarios' => ['Administración', 'Inventarios'],
        'admin.plan_compras' => ['Administración', 'Plan de compras'],
        'admin.ordenes_compra' => ['Administración', 'Órdenes de compra'],
        'admin.gastos' => ['Administración', 'Gastos'],
        'admin.shows' => ['Administración', 'Shows'],
        'admin.villa_gesell' => ['Administración', 'Villa Gesell'],
        'admin.disenos' => ['Administración', 'Diseño'],
    ];

    public const ROLES_ALTA = [
        'profesor' => 'Profesor',
        'admin' => 'Administración',
        'direccion' => 'Dirección',
        'alumno' => 'Alumno (login)',
    ];

    public function index(Request $request)
    {
        $users = User::query()->orderBy('name')->orderBy('username')->get();
        $userId = $request->integer('user_id') ?: ($users->first()?->id ?? null);
        $usuario = $userId ? $users->firstWhere('id', $userId) : null;

        $map = $usuario && is_array($usuario->modulos_access) ? $usuario->modulos_access : [];

        // Agrupar por "grupo" (primer elemento del array etiqueta)
        $agrupado = [];
        foreach (self::MODULOS as $clave => $labelParts) {
            $grupo = $labelParts[0] ?? 'General';
            $etiqueta = $labelParts[1] ?? ($labelParts[0] ?? $clave);
            $agrupado[$grupo][] = [
                'clave' => $clave,
                'etiqueta' => $etiqueta,
                'valor' => array_key_exists($clave, $map) ? (bool) $map[$clave] : true, // default permitido
            ];
        }
        ksort($agrupado);

        return view('accesos.index', compact('users', 'usuario', 'agrupado'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'access' => 'nullable|array',
            'telefono' => 'nullable|string|max:20',
        ]);

        $usuario = User::query()->findOrFail($request->integer('user_id'));

        $incoming = $request->input('access', []);
        if (! is_array($incoming)) {
            $incoming = [];
        }

        // Guardamos solo claves conocidas, para evitar basura.
        $out = [];
        foreach (array_keys(self::MODULOS) as $clave) {
            if (array_key_exists($clave, $incoming)) {
                $out[$clave] = (bool) $incoming[$clave];
            }
        }

        $usuario->forceFill(['modulos_access' => $out]);

        if ($usuario->isAdmin() && $request->has('telefono')) {
            $usuario->telefono = trim((string) $request->input('telefono')) ?: null;
        }

        $usuario->saveQuietly();

        return redirect()
            ->route('accesos.index', ['user_id' => $usuario->id])
            ->with('success', 'Accesos actualizados.');
    }

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
            ->route('accesos.index', ['user_id' => $user->id])
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
