<?php

namespace App\Domain\Acceso;

use App\Domain\Personas\RegistroAlumnoService;
use App\Models\Auditoria;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Recuperar la contraseña sin pasar por administración. Se manda un código de 6 números por
 * correo (si la cuenta tiene uno real) o por WhatsApp. Vence en 15 minutos, admite 5 intentos
 * y al confirmar cierra todas las sesiones abiertas.
 *
 * Nunca revela si la cuenta existe: iniciar() responde igual en todos los casos.
 */
final class RecuperacionClaveService
{
    public const MINUTOS_VIGENCIA = 15;

    public const MAX_INTENTOS = 5;

    public const CODIGO_INVALIDO = 'El código no es válido o venció. Pedí uno nuevo.';

    /** Mismo texto siempre: no deja averiguar qué cuentas existen. */
    public const RESPUESTA_GENERICA = 'Si los datos corresponden a una cuenta, te mandamos un código por correo o por WhatsApp. Revisá también la carpeta de spam.';

    public function __construct(private readonly DispositivosPush $dispositivos) {}

    /** Busca la cuenta por usuario, correo o DNI (con o sin puntos). */
    public function buscarCuenta(string $usuario): ?User
    {
        $usuario = trim($usuario);
        if ($usuario === '') {
            return null;
        }
        if (preg_match('/^[\d.\s-]+$/', $usuario)) {
            $usuario = RegistroAlumnoService::soloNumeros($usuario);
        }

        return User::query()->where('username', $usuario)->orWhere('email', $usuario)->first();
    }

    /** @return array{canal: string|null} canal usado; null si la cuenta no tiene a dónde mandarlo. */
    public function iniciar(string $usuario): array
    {
        $user = $this->buscarCuenta($usuario);
        if (! $user || ! $user->activo) {
            return ['canal' => null];
        }

        $correo = $this->correoReal($user);
        $telefono = $user->telefono ?: $user->persona?->telefono;
        $whatsapp = app(WhatsAppService::class);

        $canal = match (true) {
            $correo !== null => 'email',
            filled($telefono) && $whatsapp->isConfigured() => 'whatsapp',
            default => null,
        };
        if ($canal === null) {
            return ['canal' => null];
        }

        $codigo = (string) random_int(100000, 999999);
        DB::transaction(function () use ($user, $canal, $codigo) {
            $user->recuperacionesClave()->whereNull('usado_at')->update(['usado_at' => now()]);
            $user->recuperacionesClave()->create([
                'canal' => $canal,
                'codigo_hash' => self::hash($codigo),
                'vence_at' => now()->addMinutes(self::MINUTOS_VIGENCIA),
            ]);
        });

        $texto = 'Tu código para recuperar la contraseña de La Chilinga es '.$codigo.'. Vence en '.self::MINUTOS_VIGENCIA.' minutos. Si no lo pediste, ignorá este mensaje.';
        if ($canal === 'email') {
            Mail::raw($texto, fn ($m) => $m->to($correo)->subject('Código para recuperar tu contraseña'));
        } else {
            $whatsapp->send($texto, (string) $telefono, ['tipo' => 'recuperar_clave', 'user_id' => $user->id]);
        }

        Auditoria::registrar('acceso.recuperar_clave.solicitud', $user, null, ['canal' => $canal]);

        return ['canal' => $canal];
    }

    public function confirmar(string $usuario, string $codigo, string $nueva): void
    {
        $user = $this->buscarCuenta($usuario);
        $recuperacion = $user?->recuperacionesClave()
            ->whereNull('usado_at')
            ->where('vence_at', '>', now())
            ->where('intentos', '<', self::MAX_INTENTOS)
            ->latest('id')
            ->first();

        if (! $user || ! $recuperacion || ! hash_equals($recuperacion->codigo_hash, self::hash($codigo))) {
            if ($recuperacion) {
                $recuperacion->increment('intentos');
            }
            throw ValidationException::withMessages(['codigo' => self::CODIGO_INVALIDO]);
        }

        $dni = RegistroAlumnoService::soloNumeros((string) $user->persona?->dni);
        if ($nueva === $user->username || ($dni !== '' && $nueva === $dni)) {
            throw ValidationException::withMessages(['password' => 'No uses tu DNI ni tu usuario como contraseña: cualquiera que los conozca podría entrar.']);
        }

        DB::transaction(function () use ($user, $recuperacion, $nueva) {
            $recuperacion->forceFill(['usado_at' => now()])->save();
            $user->forceFill(['password' => Hash::make($nueva), 'debe_cambiar_clave' => false])->save();
            $user->tokens()->delete();
            $this->dispositivos->limpiarHuerfanos($user);
        });

        Auditoria::registrar('acceso.recuperar_clave.confirmada', $user, null, []);
    }

    /** Correo que sirve para mandar el código (no las direcciones «.invalid» de cuentas sin correo). */
    public function correoReal(User $user): ?string
    {
        $correo = trim((string) ($user->email ?: $user->persona?->email));
        if ($correo === '' || str_ends_with(strtolower($correo), '.invalid')) {
            return null;
        }

        return $correo;
    }

    public static function hash(string $codigo): string
    {
        return hash_hmac('sha256', trim($codigo), (string) config('app.key'));
    }
}
