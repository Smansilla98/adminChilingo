<?php

namespace App\Console\Commands;

use App\Domain\Acceso\CatalogoPermisos;
use App\Domain\Personas\BackfillPersonas;
use Illuminate\Console\Command;

class PersonasBackfillCommand extends Command
{
    protected $signature = 'chilinga:personas:backfill {--simular : Solo informa qué haría, sin escribir}';

    protected $description = 'Crea y vincula personas para alumnos, profesores y usuarios que todavía no tienen (idempotente).';

    public function handle(BackfillPersonas $backfill): int
    {
        if (! $this->option('simular')) {
            CatalogoPermisos::sincronizar();
        }
        $stats = $backfill->ejecutar((bool) $this->option('simular'));
        $this->table(['Concepto', 'Cantidad'], collect($stats)->map(fn ($v, $k) => [$k, $v])->values()->all());
        $this->info($this->option('simular') ? 'Simulación: no se escribió nada.' : 'Listo. Revisá duplicados con php artisan chilinga:diagnose');

        return self::SUCCESS;
    }
}
