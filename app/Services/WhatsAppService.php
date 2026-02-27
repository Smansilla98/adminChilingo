<?php

namespace App\Services;

use Twilio\Rest\Client;
use Twilio\Exceptions\TwilioException;

class WhatsAppService
{
    protected ?Client $client = null;

    protected string $from;

    public function __construct()
    {
        $sid = config('services.twilio.account_sid');
        $token = config('services.twilio.auth_token');
        $this->from = trim(config('services.twilio.whatsapp_from', ''));

        if ($sid && $token) {
            $this->client = new Client($sid, $token);
        }
    }

    /**
     * Indica si el servicio está configurado.
     */
    public function isConfigured(): bool
    {
        return $this->client !== null && $this->from !== '';
    }

    /**
     * Normaliza un número para WhatsApp: código país + número sin espacios/guiones.
     * Si no tiene + ni código país, se asume Argentina (+54).
     */
    public static function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[\s\-\(\)]/', '', $phone);
        if ($phone === '') {
            return '';
        }
        if (str_starts_with($phone, '+')) {
            return $phone;
        }
        if (preg_match('/^0?9\d{9}$/', $phone)) {
            return '+54' . preg_replace('/^0/', '', $phone);
        }
        if (preg_match('/^\d{10,15}$/', $phone)) {
            return '+' . $phone;
        }
        return '+54' . $phone;
    }

    /**
     * Envía un mensaje de texto por WhatsApp.
     *
     * @param  string  $body  Texto del mensaje
     * @param  string  $to  Número destino (ej. +5491112345678 o 9111234567)
     * @return array{success: bool, sid?: string, error?: string}
     */
    public function send(string $body, string $to): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'WhatsApp no configurado (TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, TWILIO_WHATSAPP_FROM).'];
        }

        $toNormalized = self::normalizePhone($to);
        if ($toNormalized === '') {
            return ['success' => false, 'error' => 'Número de destino inválido.'];
        }

        $from = 'whatsapp:' . $this->from;
        $toWhatsApp = 'whatsapp:' . $toNormalized;

        try {
            $message = $this->client->messages->create($toWhatsApp, [
                'from' => $from,
                'body' => $body,
            ]);
            return ['success' => true, 'sid' => $message->sid];
        } catch (TwilioException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
