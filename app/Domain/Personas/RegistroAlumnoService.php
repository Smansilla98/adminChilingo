<?php

namespace App\Domain\Personas;

use App\Domain\Acceso\AjustesCuentas;
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
    public function __construct(private readonly CodigoAltaService $codigos) {}

    /** Dominio reservado (RFC 2606): nunca recibe correos. Para quien no tiene correo. */
    public const DOMINIO_SIN_CORREO = 'alumnos.lachilinga.invalid';

    /**
     * Mismo mensaje si no hay alumno con esos datos, si ya tiene cuenta o si el código no sirve:
     * así nadie puede averiguar quién está inscripto probando DNIs.
     */
    public const NO_ENCONTRADO = 'No pudimos crear la cuenta con esos datos. Revisá el DNI, la fecha de nacimiento y el código (si te dieron uno). Si ya tenés cuenta, entrá con tu DNI o usá «Olvidé mi contraseña». Si sigue sin funcionar, pedile a tu profe o a administración.';

    public const MENOR = 'Si tenés menos de 13 años, la cuenta te la da la escuela: pedile a tu profe o a administración el acceso a la app.';

    /**
     * @param  array{dni: string, fecha_nacimiento: string, email?: string|null, password: string, codigo?: string|null}  $datos
     */
    public function registrar(array $datos): User
    {
        $dni = self::soloNumeros($datos['dni']);
        $fecha = Carbon::parse($datos['fecha_nacimiento']);
        $nacimiento = $fecha->toDateString();

        // Depende solo de lo que escribió: no revela si el alumno existe.
        if ($fecha->diffInYears(now()) < AjustesCuentas::EDAD_MINIMA_REGISTRO) {
            throw ValidationException::withMessages(['fecha_nacimiento' => self::MENOR]);
        }

        $alumno = $this->buscar($dni, $nacimiento);
        $persona = $alumno?->persona;
        $yaTiene = $alumno && (($alumno->user_id && User::query()->whereKey($alumno->user_id)->exists())
            || ($persona && User::query()->where('persona_id', $persona->id)->exists()));

        $codigoEscrito = trim((string) ($datos['codigo'] ?? ''));
        $codigo = null;
        if ($codigoEscrito !== '' || AjustesCuentas::registroRequiereCodigo()) {
            $codigo = $persona ? $this->codigos->vigente($persona, $codigoEscrito) : null;
            if (! $codigo) {
                $alumno = null;
            }
        }

        if (! $alumno || $yaTiene) {
            throw ValidationException::withMessages(['dni' => self::NO_ENCONTRADO]);
        }

        $email = trim((string) ($datos['email'] ?? ''));
        if ($email !== '' && User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'Ese correo ya lo usa otra cuenta. Escribí otro o dejalo vacío.']);
        }

        return DB::transaction(function () use ($alumno, $persona, $dni, $nacimiento, $email, $datos, $codigo) {
            if ($codigo) {
                $this->codigos->marcarUsado($codigo);
            }
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
                'username' => self::usuarioLibre($dni),
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

    public static function usuarioLibre(string $dni): string
    {
        $usuario = $dni;
        $n = 2;
        while (User::query()->where('username', $usuario)->exists()) {
            $usuario = $dni.'-'.$n++;
        }

        return $usuario;
    }

    /** @return array<string, string> mensajes de validación del formulario de registro (web y app) */
    public static function mensajes(): array
    {
        return [
            'dni.required' => 'Escribí tu DNI.',
            'fecha_nacimiento.required' => 'Elegí tu fecha de nacimiento.',
            'fecha_nacimiento.before' => 'La fecha de nacimiento tiene que ser anterior a hoy.',
            'password.required' => 'Elegí una contraseña.',
            'password.min' => 'La contraseña tiene que tener al menos 8 letras o números.',
            'codigo.max' => 'El código de alta tiene 6 letras o números.',
            'password.confirmed' => 'Las dos contraseñas no coinciden. Escribilas de nuevo.',
        ];
    }

    /** @return array<string, mixed> reglas comunes del registro (web y app) */
    public static function reglas(): array
    {
        return [
            'dni' => ['required', 'string', 'max:20'],
            'fecha_nacimiento' => ['required', 'date', 'before:today'],
            'email' => ['nullable', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', 'min:'.AjustesCuentas::CLAVE_MINIMA, 'max:255'],
            'codigo' => ['nullable', 'string', 'max:12'],
        ];
    }

    public static function soloNumeros(string $valor): string
    {
        return (string) preg_replace('/\D+/', '', $valor);
    }
}
