<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuota asociada a un bloque; opcionalmente a alumnos concretos de ese bloque.
     */
    public function up(): void
    {
        if (Schema::hasTable('cuotas') && !Schema::hasColumn('cuotas', 'bloque_id')) {
            Schema::table('cuotas', function (Blueprint $table) {
                $table->foreignId('bloque_id')->nullable()->after('id')->constrained('bloques')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('cuota_alumno')) {
            Schema::create('cuota_alumno', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cuota_id')->constrained('cuotas')->cascadeOnDelete();
                $table->foreignId('alumno_id')->constrained('alumnos')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['cuota_id', 'alumno_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cuota_alumno');
        if (Schema::hasTable('cuotas') && Schema::hasColumn('cuotas', 'bloque_id')) {
            Schema::table('cuotas', function (Blueprint $table) {
                $table->dropForeign(['bloque_id']);
            });
        }
    }
};
