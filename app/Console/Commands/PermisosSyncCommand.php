<?php

namespace App\Console\Commands;

use App\Domain\Acceso\CatalogoPermisos;
use Illuminate\Console\Command;

class PermisosSyncCommand extends Command
{
    protected $signature = 'chilinga:permisos:sync';

    protected $description = 'Vuelca config/permisos.php (permisos y roles) a las tablas de Spatie. Idempotente.';

    public function handle(): int
    {
        $r = CatalogoPermisos::sincronizar();
        $this->info("Permisos: {$r['permisos']} · Roles: {$r['roles']}");

        return self::SUCCESS;
    }
}
