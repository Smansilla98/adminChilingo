<?php

namespace App\Domain\Personas;

use App\Models\Alumno;
use App\Models\Auditoria;
use App\Models\Persona;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Un alumno que la escuela ya cargó crea su propia cuenta.
 *
 * Se identifica con DNI y fecha de nacimiento, que tienen que coincidir con su ficha. No se
 * crean alumnos nuevos ni se asignan roles: es alumno porque está inscripto en un bloque.
 * El usuario para entrar es el DNI (sin puntos), lo más fácil de recordar.
 */
class RegistroAlumnoService
{
    /** Dominio reservado (RFC 2606): nunca recibe correos. Para quien no tiene correo. */
    public const DOMINIO_SIN_CORREO = 'alumnos.lachilinga.invalid';

    public const NO_ENCONTRADO = 'No encontramos un alumno con ese DNI y esa fecha de nacimiento. Revisá que estén bien escritos. Si están bien, pedile a tu profe o a administración que los carguen en tu ficha.';

    /**
     * @param  array{dni: string, fecha_nacimiento: string, email?: string|null, password: string}  $datos
     */
    public function registrar(array $datos): User
    {
        $dni = self::soloNumeros($datos['dni']);
        $nacimiento = Carbon::parse($datos['fecha_nacimiento'])->toDateString();

        $alumno = $this->buscar($dni, $nacimiento);
        if (! $alumno) {
            throw ValidationException::withMessages(['dni' => self::NO_ENCONTRADO]);
        }

        $persona = $alumno->persona;
        $yaTiene = ($alumno->user_id && User::query()->whereKey($alumno->user_id)->exists())
            || ($persona && User::query()->where('persona_id', $persona->id)->exists());
        if ($yaTiene) {
            throw ValidationException::withMessages(['dni' => 'Ya tenés una cuenta. Entrá con tu DNI y tu contraseña. Si no te acordás la contraseña, pedile a tu profe o a administración que te la cambie.']);
        }

        $email = trim((string) ($datos['email'] ?? ''));
        if ($email !== '' && User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'Ese correo ya lo usa otra cuenta. Escribí otro o dejalo vacío.']);
        }

        return DB::transaction(function () use ($alumno, $persona, $dni, $nacimiento, $email, $datos) {
            if (! $persona) {
                $persona = Persona::query()->create([
                    'nombre' => $alumno->nombre_apellido,
                    'dni' => $dni,
                    'fecha_nacimiento' => $nacimiento,
                    'telefono' => $alumno->telefono,
                    'estado' => 'activo',
                ]);
                $alumno->forceFill(['persona_id' => $persona->id])->save();
            }

            $user = User::query()->create([
                'persona_id' => $persona->id,
                'name' => $persona->nombre_completo ?: $alumno->nombre_apellido,
                'username' => $this->usuarioLibre($dni),
                'email' => $email !== '' ? $email : $dni.'@'.self::DOMINIO_SIN_CORREO,
                'telefono' => $persona->telefono ?? $alumno->telefono,
                'password' => Hash::make($datos['password']),
                'role' => 'usuario',
            ]);
            $alumno->forceFill(['user_id' => $user->id])->save();

            Auditoria::registrar('registro.alumno', $user, null, ['alumno_id' => $alumno->id, 'username' => $user->username]);

            return $user;
        });
    }

    /** Alumno activo cuyo DNI y fecha de nacimiento (de su ficha o de su persona) coinciden. */
    private function buscar(string $dni, string $nacimiento): ?Alumno
    {
        if (strlen($dni) < 6) {
            return null;
        }
        $variantes = array_values(array_unique([$dni, number_format((float) $dni, 0, '', '.')]));

        return Alumno::query()
            ->where('activo', true)
            ->where(fn ($q) => $q->whereIn('dni', $variantes)
                ->orWhereHas('persona', fn ($p) => $p->whereIn('dni', $variantes)))
            ->with('persona')
            ->get()
            ->first(function (Alumno $a) use ($nacimiento) {
                $fechas = array_filter([$a->fecha_nacimiento, $a->persona?->fecha_nacimiento]);

                return collect($fechas)->contains(fn ($f) => Carbon::parse($f)->toDateString() === $nacimiento);
            });
    }

    private function usuarioLibre(string $dni): string
    {
        $usuario = $dni;
        $n = 2;
        while (User::query()->where('username', $usuario)->exists()) {
            $usuario = $dni.'-'.$n++;
        }

        return $usuario;
    }

    public static function soloNumeros(string $valor): string
    {
        return (string) preg_replace('/\D+/', '', $valor);
    }
}
