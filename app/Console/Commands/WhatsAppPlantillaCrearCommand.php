<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class WhatsAppPlantillaCrearCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'whatsapp:plantilla-crear
                            {--nombre=prueba_chilinga : Nombre interno de la plantilla}
                            {--idioma=es : Código de idioma}';

    /**
     * @var string
     */
    protected $description = 'Crea por API una plantilla de texto de Twilio Content ({{1}}) y muestra su ContentSid';

    public function handle(): int
    {
        $sid = (string) config('services.twilio.account_sid');
        $token = (string) config('services.twilio.auth_token');
        if ($sid === '' || $token === '') {
            $this->error('Faltan TWILIO_ACCOUNT_SID o TWILIO_AUTH_TOKEN.');

            return self::FAILURE;
        }

        $respuesta = Http::withBasicAuth($sid, $token)
            ->asJson()
            ->acceptJson()
            ->timeout(20)
            ->post('https://content.twilio.com/v1/Content', [
                'friendly_name' => (string) $this->option('nombre'),
                'language' => (string) $this->option('idioma'),
                'variables' => ['1' => 'Mensaje'],
                'types' => ['twilio/text' => ['body' => '{{1}}']],
            ]);

        if (! $respuesta->successful()) {
            $this->error('Twilio rechazó la plantilla [HTTP '.$respuesta->status().']: '.$respuesta->body());

            return self::FAILURE;
        }

        $contentSid = (string) $respuesta->json('sid');
        $this->info('Plantilla creada. ContentSid: '.$contentSid);
        $this->line('Probala con: php artisan whatsapp:test +5491126038245 --plantilla-sid='.$contentSid);
        $this->line('O guardala en Railway como TWILIO_WHATSAPP_CONTENT_SID='.$contentSid);

        return self::SUCCESS;
    }
}
