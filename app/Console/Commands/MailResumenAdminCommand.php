<?php

namespace App\Console\Commands;

use App\Services\MailResumenAdminService;
use Illuminate\Console\Command;

class MailResumenAdminCommand extends Command
{
    protected $signature = 'mail:resumen-admin
                            {--to= : Email destino (override)}
                            {--dry-run : Mostrar vista previa sin enviar}';

    protected $description = 'Envía por email el resumen semanal de pendientes (asistencias y cuotas) a administradores';

    public function handle(MailResumenAdminService $servicio): int
    {
        $to = $this->option('to');
        $dryRun = (bool) $this->option('dry-run');

        $resultado = $servicio->enviar(is_string($to) ? $to : null, $dryRun);

        if ($dryRun) {
            foreach ($resultado['detalles'] as $detalle) {
                $this->line('--- '.($detalle['email'] ?? '').' ---');
                $this->line($detalle['preview_subject'] ?? '');
                $this->newLine();
                $this->line($detalle['preview'] ?? '');
                $this->newLine();
            }
        } else {
            foreach ($resultado['detalles'] as $detalle) {
                if ($detalle['success'] ?? false) {
                    $this->info('Enviado a '.($detalle['email'] ?? ''));
                } else {
                    $this->warn(($detalle['email'] ?? '').': '.($detalle['error'] ?? 'Error'));
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

