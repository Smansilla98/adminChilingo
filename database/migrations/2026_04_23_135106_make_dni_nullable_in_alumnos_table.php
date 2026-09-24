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
        if (! Schema::hasTable('alumnos') || ! Schema::hasColumn('alumnos', 'dni')) {
            return;
        }

        // Evitamos depender de doctrine/dbal: usamos SQL directo.
        // En MySQL, un índice unique permite múltiples NULL.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE alumnos MODIFY dni VARCHAR(255) NULL');

            return;
        }

        Schema::table('alumnos', function (Blueprint $table) {
            $table->string('dni')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('alumnos') || ! Schema::hasColumn('alumnos', 'dni')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE alumnos MODIFY dni VARCHAR(255) NOT NULL');
        }
    }
};
