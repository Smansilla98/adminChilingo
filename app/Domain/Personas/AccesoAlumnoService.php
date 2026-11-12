<?php

namespace App\Domain\Personas;

use App\Domain\Acceso\UsuarioService;
use App\Models\Auditoria;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Acceso a la app para un alumno que no puede (o no sabe) registrarse solo: administración le da
 * una contraseña provisoria de 6 números y el alumno la cambia la primera vez que entra.
 * Si ya tiene cuenta, se le genera una provisoria nueva («olvidé mi contraseña»).
 * El usuario es el DNI, igual que en el registro.
 */
class AccesoAlumnoService
{
    public function __construct(private readonly UsuarioService $usuarios) {}

    public function puedeDarAcceso(User $por, Persona $persona): bool
    {
        if (! $persona->alumnos()->where('activo', true)->exists()) {
            return false;
        }

        return $persona->user
            ? $por->can('update', $persona->user)
            : $por->acceso()->puede('usuarios.create');
    }

    /** @return array{usuario: string, clave: string, nueva: bool} */
    public function darAcceso(Persona $persona, User $por): array
    {
        $persona->loadMissing(['user', 'alumnos']);
        if (! $persona->alumnos->contains(fn ($a) => $a->activo)) {
            throw ValidationException::withMessages(['persona' => 'Solo se da acceso a la app a alumnos activos.']);
        }
        if (! $this->puedeDarAcceso($por, $persona)) {
            throw new AuthorizationException('No podés dar acceso a esta cuenta.');
        }

        $clave = (string) random_int(100000, 999999);
        $user = $persona->user;
        $nueva = $user === null;

        DB::transaction(function () use ($persona, &$user, $clave) {
            if ($user) {
                $this->usuarios->resetearContrasena($user, $clave);
                $user->forceFill(['debe_cambiar_clave' => true, 'activo' => true])->save();

                return;
            }
            $alumno = $persona->alumnos->firstWhere('activo', true);
            $dni = RegistroAlumnoService::soloNumeros((string) ($persona->dni ?: $alumno?->dni));
            $base = strlen($dni) >= 6 ? $dni : 'alumno'.$persona->id;
            $user = User::query()->create([
                'persona_id' => $persona->id,
                'name' => $persona->nombre_completo ?: (string) $alumno?->nombre_apellido,
                'username' => RegistroAlumnoService::usuarioLibre($base),
                'email' => $persona->email && ! User::query()->where('email', $persona->email)->exists()
                    ? $persona->email
                    : $base.'.'.$persona->id.'@'.RegistroAlumnoService::DOMINIO_SIN_CORREO,
                'telefono' => $persona->telefono,
                'password' => Hash::make($clave),
                'role' => 'usuario',
            ]);
            $user->forceFill(['debe_cambiar_clave' => true])->save();
            foreach ($persona->alumnos as $a) {
                if (! $a->user_id) {
                    $a->forceFill(['user_id' => $user->id])->save();
                }
            }
        });

        Auditoria::registrar($nueva ? 'acceso.alumno.alta' : 'acceso.alumno.clave_provisoria', $user, null, ['persona_id' => $persona->id, 'por' => $por->id]);

        return ['usuario' => (string) $user->username, 'clave' => $clave, 'nueva' => $nueva];
    }

    /**
     * El propio usuario cambia su contraseña (obligatorio si tenía una provisoria).
     * Cierra las demás sesiones y tokens: quien tuviera la provisoria queda afuera.
     */
    public function cambiarPropia(User $user, string $actual, string $nueva, ?int $tokenActualId = null): void
    {
        if (! Hash::check($actual, $user->password)) {
            throw ValidationException::withMessages(['actual' => 'La contraseña actual no es correcta.']);
        }
        if ($nueva === $actual) {
            throw ValidationException::withMessages(['password' => 'Elegí una contraseña distinta de la actual.']);
        }
        $dni = RegistroAlumnoService::soloNumeros((string) $user->persona?->dni);
        if ($nueva === $user->username || ($dni !== '' && $nueva === $dni)) {
            throw ValidationException::withMessages(['password' => 'No uses tu DNI ni tu usuario como contraseña: cualquiera que los conozca podría entrar.']);
        }

        $user->forceFill(['password' => Hash::make($nueva), 'debe_cambiar_clave' => false])->save();
        $user->tokens()->when($tokenActualId, fn ($q) => $q->whereKeyNot($tokenActualId))->delete();
        app(\App\Domain\Acceso\DispositivosPush::class)->limpiarHuerfanos($user);
        Auditoria::registrar('acceso.cambio_clave', $user, null, []);
    }

    /** @return array<string, mixed> */
    public static function reglasCambio(): array
    {
        return [
            'actual' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:'.\App\Domain\Acceso\AjustesCuentas::CLAVE_MINIMA, 'max:255', 'confirmed'],
        ];
    }

    /** @return array<string, string> */
    public static function mensajesCambio(): array
    {
        return [
            'actual.required' => 'Escribí tu contraseña actual (o la provisoria que te dieron).',
            'password.required' => 'Elegí una contraseña nueva.',
            'password.min' => 'La contraseña tiene que tener al menos 8 letras o números.',
            'password.confirmed' => 'Las dos contraseñas nuevas no coinciden.',
        ];
    }
}
