<?php

namespace App\Domain\Notificaciones;

use App\Models\Dispositivo;
use App\Models\User;
use App\Notifications\AvisoNotification;
use App\Services\WhatsAppService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Punto único para avisar a una persona por uno o más canales.
 *
 * Cada envío tiene una clave de negocio (ej. "cuota_vencida:12:340"). La misma clave no se
 * envía dos veces por el mismo canal: tabla `notificacion_envios` con índice único.
 */
class NotificacionService
{
    public const CANALES = ['interna', 'push', 'mail', 'whatsapp'];

    public function __construct(private readonly WhatsAppService $whatsapp) {}

    /**
     * @param  array{tipo: string, titulo: string, mensaje: string, enlace?: ?string, datos?: array<string, mixed>}  $contenido
     * @param  list<string>  $canales
     * @return array<string, string> canal => enviado|duplicado|omitido|error
     */
    public function enviar(User $destino, string $clave, array $contenido, array $canales = ['interna', 'push']): array
    {
        $resultado = [];
        if (isset($destino->activo) && ! $destino->activo) {
            return array_fill_keys($canales, 'omitido');
        }

        foreach (array_intersect($canales, self::CANALES) as $canal) {
            if (! $this->reservar($clave, $canal, $destino)) {
                $resultado[$canal] = 'duplicado';

                continue;
            }
            try {
                $ok = match ($canal) {
                    'interna' => $this->interna($destino, $contenido),
                    'push' => $this->push($destino, $contenido),
                    'mail' => $this->mail($destino, $contenido),
                    'whatsapp' => $this->whatsapp($destino, $contenido),
                };
                $resultado[$canal] = $ok ? 'enviado' : 'omitido';
                if (! $ok) {
                    $this->liberar($clave, $canal); // se puede reintentar cuando el canal esté disponible
                }
            } catch (\Throwable $e) {
                Log::warning('Notificación fallida', ['canal' => $canal, 'clave' => $clave, 'error' => $e->getMessage()]);
                $this->liberar($clave, $canal);
                $resultado[$canal] = 'error';
            }
        }

        return $resultado;
    }

    private function reservar(string $clave, string $canal, User $destino): bool
    {
        try {
            DB::table('notificacion_envios')->insert([
                'clave' => mb_substr($canal.':'.$clave, 0, 191),
                'user_id' => $destino->id,
                'canal' => $canal,
                'estado' => 'enviado',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }

    private function liberar(string $clave, string $canal): void
    {
        DB::table('notificacion_envios')->where('clave', mb_substr($canal.':'.$clave, 0, 191))->delete();
    }

    private function interna(User $destino, array $contenido): bool
    {
        $destino->notify(new AvisoNotification($contenido));

        return true;
    }

    private function push(User $destino, array $contenido): bool
    {
        if (! config('services.expo.push_habilitado')) {
            return false;
        }
        $tokens = Dispositivo::query()->where('user_id', $destino->id)->pluck('token')->all();
        if ($tokens === []) {
            return false;
        }
        $mensajes = array_map(fn ($t) => [
            'to' => $t,
            'title' => $contenido['titulo'],
            'body' => $contenido['mensaje'],
            'data' => ['tipo' => $contenido['tipo'], 'enlace' => $contenido['enlace'] ?? null],
            'sound' => 'default',
        ], $tokens);

        $req = Http::timeout(10)->acceptJson();
        if ($token = config('services.expo.access_token')) {
            $req = $req->withToken($token);
        }
        $resp = $req->post(config('services.expo.url'), $mensajes);

        // Tokens dados de baja por Expo: se eliminan para no reintentar.
        foreach ((array) $resp->json('data', []) as $i => $ticket) {
            if (($ticket['details']['error'] ?? null) === 'DeviceNotRegistered' && isset($tokens[$i])) {
                Dispositivo::query()->where('token', $tokens[$i])->delete();
            }
        }

        return $resp->successful();
    }

    private function mail(User $destino, array $contenido): bool
    {
        $email = $destino->email ?: $destino->persona?->email;
        if (! $email) {
            return false;
        }
        Mail::raw($contenido['mensaje'], fn ($m) => $m->to($email)->subject($contenido['titulo']));

        return true;
    }

    private function whatsapp(User $destino, array $contenido): bool
    {
        $telefono = $destino->telefono ?: $destino->persona?->telefono;
        if (! $telefono || ! $this->whatsapp->isConfigured()) {
            return false;
        }

        return (bool) ($this->whatsapp->send($contenido['titulo']."\n".$contenido['mensaje'], $telefono, ['tipo' => $contenido['tipo']])['success'] ?? false);
    }
}
