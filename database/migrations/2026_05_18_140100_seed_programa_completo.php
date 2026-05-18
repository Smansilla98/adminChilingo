<?php

use Database\Seeders\ProgramaRitmosSeeder;
use Database\Seeders\ProgramaSeccionesSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('programa_secciones')) {
            ProgramaSeccionesSeeder::poblarSiVacio();
        }
        if (Schema::hasTable('programa_ritmos')) {
            ProgramaRitmosSeeder::poblarSiVacio();
            ProgramaRitmosSeeder::asegurarSlugs();
        }
    }

    public function down(): void
    {
        //
    }
};
