<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('alumnos')) {
            return;
        }

        if (Schema::hasColumn('alumnos', 'fecha_nacimiento')) {
            DB::statement('ALTER TABLE alumnos MODIFY fecha_nacimiento DATE NULL');
        }
        if (Schema::hasColumn('alumnos', 'sede_id')) {
            DB::statement('ALTER TABLE alumnos MODIFY sede_id BIGINT UNSIGNED NULL');
        }
        if (Schema::hasColumn('alumnos', 'instrumento_principal')) {
            DB::statement('ALTER TABLE alumnos MODIFY instrumento_principal VARCHAR(255) NULL');
        }
    }

    public function down(): void
    {
        // No revertimos a NOT NULL: puede haber filas nulas de altas rápidas.
    }
};
