<?php

namespace App\Domain\Acceso;

use App\Models\Auditoria;
use App\Models\Dispositivo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Celulares que reciben avisos push. Un token de Expo es de un solo celular y de una sola
 * cuenta a la vez: si otra cuenta entra en ese celular, el token pasa a la nueva y se borra
 * de la anterior. Solo se reasigna si quien lo pide entró en la app con su sesión (token de
 * Sanctum), que es lo que pasa al cambiar de cuenta en el mismo celular. Al cerrar la
 * sesión, el celular se borra.
 */
final class DispositivosPush
{
    /** @param  array{token: string, plataforma?: string|null, nombre?: string|null}  $datos */
    public function registrar(User $user, ?object $sesion, array $datos): Dispositivo
    {
        $sesionId = $sesion instanceof PersonalAccessToken && is_numeric($sesion->getKey()) ? (int) $sesion->getKey() : null;
        $deApp = $sesion instanceof PersonalAccessToken;

        return DB::transaction(function () use ($user, $datos, $sesionId, $deApp) {
            $existente = Dispositivo::query()->where('token', $datos['token'])->lockForUpdate()->first();

            if ($existente && (int) $existente->user_id !== (int) $user->id) {
                if (! $deApp) {
                    throw new HttpException(409, 'Ese celular está registrado en otra cuenta. Entrá desde la app para recibir los avisos acá.');
                }
                Auditoria::registrar('dispositivo.reasignado', $user, ['user_id' => $existente->user_id], ['user_id' => $user->id]);
                $existente->delete();
                $existente = null;
            }

            $valores = [
                'user_id' => $user->id,
                'plataforma' => $datos['plataforma'] ?? null,
                'nombre' => $datos['nombre'] ?? null,
                'ultimo_uso_at' => now(),
            ];
            if ($sesionId !== null) {
                $valores['personal_access_token_id'] = $sesionId;
            }

            if ($existente) {
                $existente->forceFill($valores)->save();

                return $existente;
            }

            return Dispositivo::query()->forceCreate($valores + ['token' => $datos['token']]);
        });
    }

    /** Cierre de sesión en la app: se borran los celulares de esa sesión (y el token indicado). */
    public function cerrarSesion(User $user, ?object $sesion, ?string $pushToken = null): void
    {
        if ($sesion instanceof PersonalAccessToken && is_numeric($sesion->getKey())) {
            $user->dispositivos()->where('personal_access_token_id', (int) $sesion->getKey())->delete();
        }
        if (filled($pushToken)) {
            $user->dispositivos()->where('token', $pushToken)->delete();
        }
    }

    /** Al rotar el token de la sesión, los celulares siguen asociados a la sesión nueva. */
    public function rotarSesion(int $anterior, int $nueva): void
    {
        Dispositivo::query()->where('personal_access_token_id', $anterior)->update(['personal_access_token_id' => $nueva]);
    }

    /** Celulares cuya sesión ya no existe (se cerraron las sesiones al cambiar la clave). */
    public function limpiarHuerfanos(User $user): void
    {
        $vigentes = $user->tokens()->pluck('id')->all();
        $user->dispositivos()->whereNotNull('personal_access_token_id')
            ->whereNotIn('personal_access_token_id', $vigentes ?: [0])->delete();
    }
}
