<?php

namespace App\Console\Commands;

use App\Models\WhatsappMensaje;
use App\Services\WhatsAppService;
use Illuminate\Console\Command;

class SendWhatsAppTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:test
                            {numero : Número destino (ej. +5491112345678 o 9111234567)}
                            {--message= : Mensaje a enviar (por defecto: mensaje de prueba La Chilinga)}
                            {--plantilla-sid= : SID (HX...) de una plantilla de Twilio Content con la variable {{1}}; si se omite, usa TWILIO_WHATSAPP_CONTENT_SID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envía un mensaje de prueba por WhatsApp (Twilio)';

    public function handle(WhatsAppService $whatsapp): int
    {
        $numero = $this->argument('numero');
        $message = $this->option('message') ?: 'Hola, este es un mensaje de prueba del sistema de gestión La Chilinga. Si lo recibiste, WhatsApp está configurado correctamente.';

        if (! $whatsapp->isConfigured()) {
            $this->error('WhatsApp no está configurado. Definí en .env: TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, TWILIO_WHATSAPP_FROM');

            return self::FAILURE;
        }

        $contentSid = trim((string) ($this->option('plantilla-sid') ?: config('services.twilio.whatsapp_content_sid', '')));

        $this->info("Enviando mensaje a {$numero}...");
        $contexto = ['tipo' => WhatsappMensaje::TIPO_TEST];
        $result = $contentSid !== ''
            ? $whatsapp->sendContentTemplate($numero, $contentSid, ['1' => $message], $contexto)
            : $whatsapp->send($message, $numero, $contexto);

        if ($result['success']) {
            $this->info('Twilio aceptó el envío. SID: '.($result['sid'] ?? '').' Estado inicial: '.($result['status'] ?? 'queued'));
            $this->line('Eso no significa que WhatsApp lo haya entregado. El estado real llega por status callback.');

            return self::SUCCESS;
        }

        $this->error('Error: '.($result['error'] ?? 'Error desconocido'));

        return self::FAILURE;
    }
}
