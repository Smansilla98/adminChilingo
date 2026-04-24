<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('alumnos') || !Schema::hasColumn('alumnos', 'dni')) {
            return;
        }

        // Evitamos depender de doctrine/dbal: usamos SQL directo.
        // En MySQL, un índice unique permite múltiples NULL.
        DB::statement('ALTER TABLE alumnos MODIFY dni VARCHAR(255) NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('alumnos') || !Schema::hasColumn('alumnos', 'dni')) {
            return;
        }

        DB::statement('ALTER TABLE alumnos MODIFY dni VARCHAR(255) NOT NULL');
    }
};
