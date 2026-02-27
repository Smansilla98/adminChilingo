<?php

namespace App\Console\Commands;

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
                            {--message= : Mensaje a enviar (por defecto: mensaje de prueba La Chilinga)}';

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

        if (!$whatsapp->isConfigured()) {
            $this->error('WhatsApp no está configurado. Definí en .env: TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, TWILIO_WHATSAPP_FROM');
            return self::FAILURE;
        }

        $this->info("Enviando mensaje a {$numero}...");
        $result = $whatsapp->send($message, $numero);

        if ($result['success']) {
            $this->info('Mensaje enviado correctamente. SID: ' . ($result['sid'] ?? ''));
            return self::SUCCESS;
        }

        $this->error('Error: ' . ($result['error'] ?? 'Error desconocido'));
        return self::FAILURE;
    }
}
