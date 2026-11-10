<?php

namespace App\Console\Commands;

use App\Domain\Notificaciones\DifusionService;
use Illuminate\Console\Command;

class DifusionesCommand extends Command
{
    protected $signature = 'chilinga:difusiones';

    protected $description = 'Manda los mensajes a grupos programados cuya hora ya llegó.';

    public function handle(DifusionService $difusiones): int
    {
        $n = $difusiones->enviarPendientes();
        $this->info("Mensajes enviados: $n");

        return self::SUCCESS;
    }
}
