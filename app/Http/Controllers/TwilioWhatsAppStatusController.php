<?php

namespace App\Http\Controllers;

use App\Models\WhatsappMensaje;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Twilio\Security\RequestValidator;

class TwilioWhatsAppStatusController extends Controller
{
    public function __invoke(Request $request): Response
    {
        if (! $this->firmaValida($request)) {
            Log::warning('WhatsApp callback: firma Twilio inválida');

            return response('Forbidden', 403);
        }

        $sid = (string) $request->input('MessageSid', $request->input('SmsSid', ''));
        $status = (string) $request->input('MessageStatus', $request->input('SmsStatus', ''));
        $errorCode = $request->input('ErrorCode');
        $errorMessage = $request->input('ErrorMessage');

        if ($sid === '') {
            return response('Missing MessageSid', 422);
        }

        if (! Schema::hasTable('whatsapp_mensajes')) {
            return response('OK', 200);
        }

        $mensaje = WhatsappMensaje::query()->where('twilio_sid', $sid)->first();
        if (! $mensaje) {
            $mensaje = $this->crearDesdeCallback($request, $sid);
        }
        if (! $mensaje) {
            Log::info('WhatsApp callback: SID desconocido', [
                'sid' => $sid,
                'status' => $status,
            ]);

            return response('OK', 200);
        }

        $updated = $mensaje->applyTwilioCallback(
            $status,
            $errorCode !== null ? (string) $errorCode : null,
            $errorMessage !== null ? (string) $errorMessage : null,
        );

        Log::info('WhatsApp callback', [
            'sid' => $sid,
            'status' => $status,
            'mensaje_id' => $mensaje->id,
            'guardado' => $mensaje->status,
            'updated' => $updated,
            'error_code' => $mensaje->error_code,
        ]);

        return response('OK', 200);
    }

    private function firmaValida(Request $request): bool
    {
        $token = (string) config('services.twilio.auth_token');
        if ($token === '') {
            return false;
        }

        $signature = (string) $request->header('X-Twilio-Signature', '');
        if ($signature === '') {
            return false;
        }

        $validator = new RequestValidator($token);
        $params = $request->post();
        $candidatos = array_unique(array_filter([
            trim((string) config('services.twilio.status_callback_url', '')),
            $request->fullUrl(),
            url('/webhooks/twilio/whatsapp-status'),
        ]));

        foreach ($candidatos as $url) {
            if ($validator->validate($signature, $url, $params)) {
                return true;
            }
        }

        return false;
    }

    /**
     * El callback a veces llega antes de persistir el SID. Guardamos una fila mínima
     * para no perder delivered/failed; el envío completa alumno/cuota después.
     */
    private function crearDesdeCallback(Request $request, string $sid): ?WhatsappMensaje
    {
        $to = (string) $request->input('To', '');
        $telefono = WhatsAppService::normalizePhone($to);
        if ($telefono === '') {
            $telefono = 'unknown';
        }

        try {
            return WhatsappMensaje::query()->create([
                'twilio_sid' => $sid,
                'telefono' => substr($telefono, 0, 32),
                'tipo' => 'callback',
                'status' => WhatsappMensaje::STATUS_QUEUED,
                'accepted_at' => now(),
            ]);
        } catch (\Throwable $e) {
            return WhatsappMensaje::query()->where('twilio_sid', $sid)->first();
        }
    }
}
