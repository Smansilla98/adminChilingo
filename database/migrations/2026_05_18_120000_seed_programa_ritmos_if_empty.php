<?php

use Database\Seeders\ProgramaRitmosSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Carga el programa oficial si la tabla existe y está vacía (útil en Railway sin seed manual).
     */
    public function up(): void
    {
        if (! Schema::hasTable('programa_ritmos')) {
            return;
        }

        if (Schema::hasTable('programa_secciones')) {
            \Database\Seeders\ProgramaSeccionesSeeder::poblarSiVacio();
        }
        ProgramaRitmosSeeder::poblarSiVacio();
        ProgramaRitmosSeeder::asegurarSlugs();
    }

    public function down(): void
    {
        // No borramos datos del programa al revertir.
    }
};
