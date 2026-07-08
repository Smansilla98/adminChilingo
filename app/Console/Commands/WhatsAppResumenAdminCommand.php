<?php

namespace App\Console\Commands;

use App\Services\WhatsAppResumenAdminService;
use Illuminate\Console\Command;

class WhatsAppResumenAdminCommand extends Command
{
    protected $signature = 'whatsapp:resumen-admin
                            {--dry-run : Mostrar mensaje y destinatarios sin enviar}';

    protected $description = 'Envía por WhatsApp el resumen semanal de pendientes (asistencias y cuotas) a administradores';

    public function handle(WhatsAppResumenAdminService $servicio): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $resultado = $servicio->enviar($dryRun);

        if ($dryRun) {
            foreach ($resultado['detalles'] as $detalle) {
                $this->line('--- '.($detalle['etiqueta'] ?? '').' ('.($detalle['telefono'] ?? '').') ---');
                $this->line($detalle['preview'] ?? '');
                $this->newLine();
            }
        } else {
            foreach ($resultado['detalles'] as $detalle) {
                if ($detalle['success'] ?? false) {
                    $this->info('Enviado a '.($detalle['etiqueta'] ?? ''));
                } else {
                    $this->warn(($detalle['etiqueta'] ?? '').': '.($detalle['error'] ?? 'Error'));
                }
            }
        }

        if ($resultado['enviados'] === 0 && $resultado['errores'] === 0) {
            $this->error($resultado['mensaje']);

            return self::FAILURE;
        }

        $this->info($resultado['mensaje']);

        return ($resultado['errores'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
