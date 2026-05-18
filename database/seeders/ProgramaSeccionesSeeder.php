<?php

namespace Database\Seeders;

use App\Models\ProgramaSeccion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class ProgramaSeccionesSeeder extends Seeder
{
    public static function poblarSiVacio(): int
    {
        if (! Schema::hasTable('programa_secciones')) {
            return 0;
        }
        if (ProgramaSeccion::query()->exists()) {
            return (int) ProgramaSeccion::query()->count();
        }

        return self::poblar();
    }

    public function run(): void
    {
        self::poblar();
    }

    public static function poblar(): int
    {
        $filas = require __DIR__.'/data/programa_secciones_contenido.php';
        foreach ($filas as $f) {
            ProgramaSeccion::updateOrCreate(
                ['slug' => $f['slug']],
                [
                    'titulo' => $f['titulo'],
                    'subtitulo' => $f['subtitulo'] ?? null,
                    'cuerpo' => $f['cuerpo'],
                    'orden' => $f['orden'],
                    'categoria' => $f['categoria'],
                    'anio' => $f['anio'] ?? null,
                    'activo' => true,
                ]
            );
        }

        return count($filas);
    }
}
