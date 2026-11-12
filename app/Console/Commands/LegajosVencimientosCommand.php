<?php

namespace App\Console\Commands;

use App\Domain\Personas\LegajoMenorService;
use Illuminate\Console\Command;

/** Avisa a la administración de los aptos médicos que vencen pronto o ya vencieron. */
class LegajosVencimientosCommand extends Command
{
    protected $signature = 'chilinga:legajos:vencimientos';

    protected $description = 'Avisa de los aptos médicos de menores que vencen en 30 días o ya vencieron';

    public function handle(LegajoMenorService $legajos): int
    {
        $n = $legajos->avisarVencimientos();
        $this->info("Avisos de apto médico enviados: {$n}.");

        return self::SUCCESS;
    }
}
