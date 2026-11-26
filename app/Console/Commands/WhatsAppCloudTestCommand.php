<?php

namespace App\Console\Commands;

use App\Services\WhatsAppService;
use Illuminate\Console\Command;

/**
 * Notificación de prueba por WhatsApp Cloud API (Etapa 1). Envía una plantilla aprobada a un número.
 * Ejemplo: php artisan whatsapp:cloud-test +5491112345678 --plantilla=hello_world --idioma=en_US
 */
class WhatsAppCloudTestCommand extends Command
{
    protected $signature = 'whatsapp:cloud-test
        {telefono : Destino en formato internacional, ej. +5491112345678}
        {--plantilla=hello_world : Nombre de la plantilla aprobada en Meta}
        {--idioma=en_US : Código de idioma de la plantilla}';

    protected $description = 'Envía una plantilla de prueba por WhatsApp Cloud API (driver cloud).';

    public function handle(WhatsAppService $whatsapp): int
    {
        if ($whatsapp->driver() !== 'cloud') {
            $this->error('El driver activo no es cloud. Definí WHATSAPP_DRIVER=cloud.');

            return self::FAILURE;
        }
        if (! $whatsapp->isConfigured()) {
            $this->error('Faltan WHATSAPP_CLOUD_TOKEN o WHATSAPP_CLOUD_PHONE_ID.');

            return self::FAILURE;
        }

        $resultado = $whatsapp->sendTemplate(
            (string) $this->argument('telefono'),
            (string) $this->option('plantilla'),
            (string) $this->option('idioma'),
            [],
            ['tipo' => 'test'],
        );

        if (! ($resultado['success'] ?? false)) {
            $this->error('No se envió: '.($resultado['error'] ?? 'error desconocido'));

            return self::FAILURE;
        }

        $this->info('Aceptado por WhatsApp Cloud API. ID: '.$resultado['sid']);

        return self::SUCCESS;
    }
}
