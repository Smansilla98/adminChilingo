<?php

namespace Database\Seeders;

use App\Services\PartiturasCuadernilloImporter;
use Illuminate\Database\Seeder;

/**
 * Asigna los PDF del Cuadernillo de Toques a cada ProgramaRitmo (medios.partitura).
 *
 * Fuente: database/data/partituras-pdf/*.pdf + manifest.json
 * Uso: php artisan db:seed --class=PartiturasCuadernilloSeeder
 *      o desde el front (admin): Partituras → «Cargar PDFs del cuadernillo»
 */
class PartiturasCuadernilloSeeder extends Seeder
{
    public function run(): void
    {
        $result = app(PartiturasCuadernilloImporter::class)->importar();

        foreach ($result['messages'] as $msg) {
            $this->command?->line($msg);
        }

        $this->command?->info("Listo: {$result['ok']} PDFs asignados, {$result['fail']} fallidas, {$result['created']} toques creados.");
    }
}
