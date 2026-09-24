<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('alumnos')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            // SQLite/Postgres (tests, instalaciones nuevas): mismo cambio con el schema builder.
            Schema::table('alumnos', function (Blueprint $table) {
                foreach (['fecha_nacimiento' => 'date', 'instrumento_principal' => 'string'] as $col => $tipo) {
                    if (Schema::hasColumn('alumnos', $col)) {
                        $table->{$tipo}($col)->nullable()->change();
                    }
                }
                if (Schema::hasColumn('alumnos', 'sede_id')) {
                    $table->unsignedBigInteger('sede_id')->nullable()->change();
                }
            });

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
