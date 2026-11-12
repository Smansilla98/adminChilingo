<?php

namespace App\Support;

use App\Models\ProgramaRitmo;
use App\Models\ProgramaSeccion;
use Database\Seeders\ProgramaRitmosSeeder;
use Database\Seeders\ProgramaSeccionesSeeder;
use Illuminate\Support\Facades\Schema;

/**
 * Carga los datos base del programa (ritmos, secciones) si la base está vacía.
 */
class ProgramaArranque
{
    public static function asegurar(): void
    {
        if (! ProgramaRitmo::query()->exists()) {
            ProgramaRitmosSeeder::poblarSiVacio();
        }
        if (Schema::hasColumn('programa_ritmos', 'slug')) {
            ProgramaRitmosSeeder::asegurarSlugs();
        }

        if (Schema::hasTable('programa_secciones') && ! ProgramaSeccion::query()->exists()) {
            ProgramaSeccionesSeeder::poblarSiVacio();
        }
    }
}
