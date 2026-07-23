<?php

namespace Database\Seeders;

use App\Services\PartiturasCuadernilloImporter;
use Illuminate\Database\Seeder;

/**
 * Carga partituras v3 del Cuadernillo de Toques en programa_ritmos.medios.partitura_vexflow.
 *
 * Fuente: database/data/partituras/*.json + manifest.json
 * Uso: php artisan db:seed --class=PartiturasCuadernilloSeeder
 *      o desde el front (admin): Partituras → «Cargar cuadernillo»
 */
class PartiturasCuadernilloSeeder extends Seeder
{
    public function run(): void
    {
        $result = app(PartiturasCuadernilloImporter::class)->importar();

        foreach ($result['messages'] as $msg) {
            if (str_starts_with($msg, 'Creado:') || str_starts_with($msg, 'Sin match') || str_starts_with($msg, 'Falta') || str_starts_with($msg, 'Partitura')) {
                $this->command?->warn($msg);
            } else {
                $this->command?->line($msg);
            }
        }

        $this->command?->info("Listo: {$result['ok']} cargadas, {$result['fail']} fallidas, {$result['created']} creadas.");
    }
}
