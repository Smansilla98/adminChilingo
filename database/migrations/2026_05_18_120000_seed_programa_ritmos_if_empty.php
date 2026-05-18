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

        // Solo datos base (sin slug). Columnas extra y slugs: migración 140000+.
        ProgramaRitmosSeeder::poblarSiVacio();
    }

    public function down(): void
    {
        // No borramos datos del programa al revertir.
    }
};
