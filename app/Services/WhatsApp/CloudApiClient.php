<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente de la WhatsApp Business Cloud API (Meta). Sólo envía plantillas aprobadas
 * (mensajes iniciados por la organización). No recibe ni responde mensajes.
 *
 * Resultado: ['success' => bool, 'wamid' => ?string, 'error' => ?string, 'codigo' => ?int, 'reintentable' => bool].
 */
final class CloudApiClient
{
    public function __construct(
        private readonly ?string $token,
        private readonly ?string $phoneNumberId,
        private readonly string $version = 'v21.0',
        private readonly string $baseUrl = 'https://graph.facebook.com',
    ) {}

    public function configurado(): bool
    {
        return filled($this->token) && filled($this->phoneNumberId);
    }

    /**
     * @param  list<string>  $parametros  valores del cuerpo de la plantilla, en orden
     * @return array{success: bool, wamid?: ?string, error?: ?string, codigo?: ?int, reintentable?: bool}
     */
    public function enviarPlantilla(string $telefonoE164, string $plantilla, string $idioma, array $parametros = []): array
    {
        if (! $this->configurado()) {
            return ['success' => false, 'error' => 'WhatsApp Cloud API no configurada (token o phone_number_id).', 'codigo' => null, 'reintentable' => false];
        }

        $template = ['name' => $plantilla, 'language' => ['code' => $idioma]];
        if ($parametros !== []) {
            $template['components'] = [[
                'type' => 'body',
                'parameters' => array_map(fn (string $v) => ['type' => 'text', 'text' => $v], array_values($parametros)),
            ]];
        }

        $url = rtrim($this->baseUrl, '/').'/'.$this->version.'/'.$this->phoneNumberId.'/messages';

        try {
            $respuesta = Http::withToken((string) $this->token)
                ->acceptJson()
                ->timeout(15)
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'to' => ltrim($telefonoE164, '+'),
                    'type' => 'template',
                    'template' => $template,
                ]);
        } catch (ConnectionException $e) {
            Log::warning('WhatsApp Cloud: sin conexión', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => 'Sin conexión con WhatsApp Cloud API.', 'codigo' => null, 'reintentable' => true];
        }

        if ($respuesta->successful()) {
            return ['success' => true, 'wamid' => $respuesta->json('messages.0.id'), 'error' => null, 'codigo' => null, 'reintentable' => false];
        }

        $codigo = $respuesta->json('error.code');
        $mensaje = (string) ($respuesta->json('error.message') ?? 'Error HTTP '.$respuesta->status());
        // 5xx, 429 y errores transitorios de Meta se reintentan; 4xx de datos (número inválido, plantilla no aprobada) no.
        $reintentable = $respuesta->serverError() || $respuesta->status() === 429;

        Log::warning('WhatsApp Cloud: envío rechazado', ['http' => $respuesta->status(), 'codigo' => $codigo, 'reintentable' => $reintentable]);

        return ['success' => false, 'error' => $mensaje, 'codigo' => is_numeric($codigo) ? (int) $codigo : null, 'reintentable' => $reintentable];
    }
}
