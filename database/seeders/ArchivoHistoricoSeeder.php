<?php

namespace Database\Seeders;

use App\Domain\Archivo\ArchivoEditorialService;
use App\Models\ArchivoCapitulo;
use Illuminate\Database\Seeder;

/**
 * Estructura de ejemplo del archivo histórico: capítulos y acontecimientos sin fotos
 * (las fotos se suben desde la gestión). No corre en producción.
 *
 *   php artisan db:seed --class=ArchivoHistoricoSeeder
 */
class ArchivoHistoricoSeeder extends Seeder
{
    public function run(ArchivoEditorialService $editorial): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('ArchivoHistoricoSeeder no corre en producción: el archivo se carga desde la gestión.');

            return;
        }
        if (ArchivoCapitulo::query()->exists()) {
            $this->command?->info('El archivo ya tiene capítulos: no se agregan ejemplos.');

            return;
        }

        $plan = [
            ['Fundación', 1995, 1995, 'Una plaza, un puñado de tambores y una idea.', [[1995, 'Los primeros tambores']]],
            ['Primeros años', 1996, 1997, 'Los ensayos se vuelven costumbre.', [[1996, 'Primeros ensayos'], [1997, 'Primeros escenarios']]],
            ['Primeras giras', 1998, 1999, 'La Chilinga sale a la ruta.', [[1998, 'Primera gira']]],
            ['Expansión de la escuela', 2000, 2009, 'Nuevas sedes y nuevas generaciones.', [[2000, 'La escuela crece']]],
            ['Nuevas generaciones', 2010, 2019, 'Quienes aprendieron, ahora enseñan.', [[2010, 'Nuevas generaciones']]],
            ['Nueva etapa', 2020, (int) now()->year, 'Treinta años de tambores.', [[2020, 'Nueva etapa']]],
        ];

        foreach ($plan as [$titulo, $desde, $hasta, $bajada, $acontecimientos]) {
            $capitulo = $editorial->guardarCapitulo(null, [
                'titulo' => $titulo, 'anio_desde' => $desde, 'anio_hasta' => $hasta, 'bajada' => $bajada, 'publicado' => true,
            ]);
            foreach ($acontecimientos as [$anio, $nombre]) {
                $editorial->guardarAcontecimiento(null, [
                    'titulo' => $nombre, 'anio' => $anio, 'capitulo_id' => $capitulo->id,
                    'descripcion' => 'Texto de ejemplo: reemplazalo por el relato real desde la gestión del archivo.',
                    'publicado' => true,
                ]);
            }
        }
    }
}
