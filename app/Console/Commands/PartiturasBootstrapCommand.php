<?php

namespace App\Console\Commands;

use Database\Seeders\PartiturasEjemploSeeder;
use Database\Seeders\ProgramaRitmosSeeder;
use Database\Seeders\ProgramaSeccionesSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class PartiturasBootstrapCommand extends Command
{
    protected $signature = 'partituras:bootstrap
                            {--force : Recargar partituras v4 aunque ya existan}';

    protected $description = 'Carga toques y partituras v4 solo si faltan (usado por start.sh)';

    public function handle(): int
    {
        if (! Schema::hasTable('programa_ritmos')) {
            $this->warn('No existe programa_ritmos. Saltando seeders de partituras.');

            return self::SUCCESS;
        }

        $secciones = ProgramaSeccionesSeeder::poblarSiVacio();
        $this->line("Secciones de programa: {$secciones}");

        $ritmos = ProgramaRitmosSeeder::poblarSiVacio();
        $this->line("Toques de programa: {$ritmos}");

        $seeder = new PartiturasEjemploSeeder;
        $seeder->setCommand($this);
        $seeder->cargar(! (bool) $this->option('force'));

        return self::SUCCESS;
    }
}
