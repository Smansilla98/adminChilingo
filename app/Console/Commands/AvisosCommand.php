<?php

namespace App\Console\Commands;

use App\Domain\Notificaciones\Avisos;
use Illuminate\Console\Command;

class AvisosCommand extends Command
{
    protected $signature = 'chilinga:avisos {tipo : cuotas-vencidas|eventos}';

    protected $description = 'Genera avisos internos/push (idempotente: no repite avisos ya enviados).';

    public function handle(Avisos $avisos): int
    {
        $n = match ($this->argument('tipo')) {
            'cuotas-vencidas' => $avisos->cuotasVencidas(),
            'eventos' => $avisos->eventosProximos(),
            default => null,
        };
        if ($n === null) {
            $this->error('Tipo desconocido. Usá cuotas-vencidas o eventos.');

            return self::FAILURE;
        }
        $this->info("Avisos nuevos: $n");

        return self::SUCCESS;
    }
}
