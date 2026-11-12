<?php

namespace App\Console\Commands;

use App\Domain\Notificaciones\Avisos;
use Illuminate\Console\Command;

class AvisosCommand extends Command
{
    protected $signature = 'chilinga:avisos {tipo : cuotas-nuevas|cuotas-por-vencer|cuotas-vencidas|eventos|eventos-nuevos|pasar-lista|ausencias|shows-convocados|shows-recordatorio}';

    protected $description = 'Genera avisos internos/push (idempotente: no repite avisos ya enviados).';

    public function handle(Avisos $avisos): int
    {
        $n = match ($this->argument('tipo')) {
            'cuotas-nuevas' => $avisos->cuotasNuevas(),
            'cuotas-por-vencer' => $avisos->cuotasPorVencer(),
            'cuotas-vencidas' => $avisos->cuotasVencidas(),
            'eventos' => $avisos->eventosProximos(),
            'eventos-nuevos' => $avisos->eventosNuevos(),
            'pasar-lista' => $avisos->pasarLista(),
            'ausencias' => $avisos->ausenciasSeguidas(),
            'shows-convocados' => app(\App\Domain\Agenda\AvisosShows::class)->convocados(),
            'shows-recordatorio' => app(\App\Domain\Agenda\AvisosShows::class)->recordatorios(),
            default => null,
        };
        if ($n === null) {
            $this->error('Tipo desconocido. Usá cuotas-nuevas, cuotas-por-vencer, cuotas-vencidas, eventos, eventos-nuevos, pasar-lista, ausencias, shows-convocados o shows-recordatorio.');

            return self::FAILURE;
        }
        $this->info("Avisos nuevos: $n");

        return self::SUCCESS;
    }
}
