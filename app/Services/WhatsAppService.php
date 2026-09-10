<?php

namespace App\Services;

use App\Models\WhatsappMensaje;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client;

class WhatsAppService
{
    protected ?Client $client = null;

    protected string $from;

    public function __construct()
    {
        $this->from = trim((string) config('services.twilio.whatsapp_from', ''));

        $sid = config('services.twilio.account_sid');
        $token = config('services.twilio.auth_token');

        if ($sid && $token) {
            $this->client = new Client($sid, $token);
        }
    }

    /**
     * Inyecta un cliente Twilio (tests). No usar en producción.
     */
    public function setClient(Client $client): void
    {
        $this->client = $client;
    }

    /**
     * Indica si el servicio está configurado.
     */
    public function isConfigured(): bool
    {
        return $this->client !== null && $this->from !== '';
    }

    /**
     * URL pública que Twilio llamará con el estado del mensaje.
     * Vacío si no hay URL alcanzable (localhost no sirve).
     */
    public function statusCallbackUrl(): ?string
    {
        $configured = trim((string) config('services.twilio.status_callback_url', ''));
        if ($configured !== '') {
            return $configured;
        }

        $appUrl = rtrim((string) config('app.url'), '/');
        if ($appUrl === '' || str_contains($appUrl, 'localhost') || str_contains($appUrl, '127.0.0.1')) {
            return null;
        }

        return $appUrl.'/webhooks/twilio/whatsapp-status';
    }

    /**
     * Normaliza un número para WhatsApp: código país + número sin espacios/guiones.
     * Si no tiene + ni código país, se asume Argentina (+54).
     */
    public static function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[\s\-\(\)]/', '', $phone) ?? '';
        $phone = preg_replace('/^whatsapp:/i', '', $phone) ?? '';
        if ($phone === '') {
            return '';
        }
        if (str_starts_with($phone, '+')) {
            return $phone;
        }
        // Móvil AR: 9XXXXXXXXX (10) o 9XXXXXXXXXX (11, 9+área+número); opcional 0 inicial.
        if (preg_match('/^0?9\d{9,10}$/', $phone)) {
            return '+54'.preg_replace('/^0/', '', $phone);
        }
        // CABA con 15: 01115XXXXXXXX o 15XXXXXXXX → +54911XXXXXXXX
        if (preg_match('/^01115(\d{8})$/', $phone, $m)) {
            return '+54911'.$m[1];
        }
        if (preg_match('/^15(\d{8})$/', $phone, $m)) {
            return '+54911'.$m[1];
        }
        // 011 + 8 dígitos (fijo CABA): evita +011...
        if (preg_match('/^011(\d{8})$/', $phone, $m)) {
            return '+5411'.$m[1];
        }
        // Ya incluye código de país 54, sin +.
        if (preg_match('/^54\d{8,13}$/', $phone)) {
            return '+'.$phone;
        }

        return '+54'.$phone;
    }

    /**
     * Envía un mensaje de texto por WhatsApp.
     *
     * success=true significa que Twilio aceptó la solicitud (hay SID), no que WhatsApp lo entregó.
     *
     * @param  array{tipo?: string, alumno_id?: int|null, user_id?: int|null, cuota_id?: int|null}  $context
     * @return array{success: bool, sid?: string, status?: string, accepted_by_provider?: bool, delivered_to_recipient?: bool, error?: string}
     */
    public function send(string $body, string $to, array $context = []): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'WhatsApp no configurado (TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, TWILIO_WHATSAPP_FROM).'];
        }

        $toNormalized = self::normalizePhone($to);
        if ($toNormalized === '') {
            return ['success' => false, 'error' => 'Número de destino inválido.'];
        }

        $from = 'whatsapp:'.$this->from;
        $toWhatsApp = 'whatsapp:'.$toNormalized;

        $payload = [
            'from' => $from,
            'body' => $body,
        ];
        $callback = $this->statusCallbackUrl();
        if ($callback) {
            $payload['statusCallback'] = $callback;
        }

        try {
            $message = $this->client->messages->create($toWhatsApp, $payload);
        } catch (TwilioException $e) {
            Log::warning('WhatsApp: Twilio rechazó el envío', [
                'telefono' => $toNormalized,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }

        $sid = (string) $message->sid;
        $status = WhatsappMensaje::normalizeStatus($message->status ?? WhatsappMensaje::STATUS_QUEUED)
            ?? WhatsappMensaje::STATUS_QUEUED;

        $row = $this->persistAccepted($sid, $toNormalized, $status, $context);

        Log::info('WhatsApp: Twilio aceptó el envío', [
            'sid' => $sid,
            'status' => $status,
            'mensaje_id' => $row?->id,
            'tipo' => $context['tipo'] ?? 'manual',
        ]);

        return [
            'success' => true,
            'sid' => $sid,
            'status' => $row?->status ?? $status,
            'accepted_by_provider' => true,
            'delivered_to_recipient' => $row?->isDeliveredToRecipient() ?? false,
        ];
    }

    /**
     * @param  array{tipo?: string, alumno_id?: int|null, user_id?: int|null, cuota_id?: int|null}  $context
     */
    protected function persistAccepted(string $sid, string $telefono, string $status, array $context): ?WhatsappMensaje
    {
        if ($sid === '' || ! Schema::hasTable('whatsapp_mensajes')) {
            return null;
        }

        try {
            $existente = WhatsappMensaje::query()->where('twilio_sid', $sid)->first();
            if ($existente) {
                $this->mergeContext($existente, $context);

                return $existente;
            }

            return WhatsappMensaje::query()->create([
                'twilio_sid' => $sid,
                'telefono' => $telefono,
                'tipo' => $context['tipo'] ?? 'manual',
                'alumno_id' => $context['alumno_id'] ?? null,
                'user_id' => $context['user_id'] ?? null,
                'cuota_id' => $context['cuota_id'] ?? null,
                'status' => $status,
                'accepted_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp: no se pudo persistir el SID', [
                'sid' => $sid,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Si el callback llegó antes que el persist, completar alumno/cuota/tipo sin tocar el estado real.
     *
     * @param  array{tipo?: string, alumno_id?: int|null, user_id?: int|null, cuota_id?: int|null}  $context
     */
    protected function mergeContext(WhatsappMensaje $existente, array $context): void
    {
        foreach (['alumno_id', 'user_id', 'cuota_id'] as $field) {
            if (! $existente->{$field} && ! empty($context[$field])) {
                $existente->{$field} = $context[$field];
            }
        }
        $tipo = $context['tipo'] ?? null;
        if ($tipo && in_array($existente->tipo, ['manual', 'callback', ''], true)) {
            $existente->tipo = $tipo;
        }
        if ($existente->isDirty()) {
            $existente->save();
        }
    }
}
