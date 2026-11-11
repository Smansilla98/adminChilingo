<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grupo familiar: alumnos de la misma familia (hermanos, madre e hijo...). Sirve para el
 * descuento por grupo familiar. Un alumno está en un solo grupo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('grupos_familiares')) {
            Schema::create('grupos_familiares', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 120);
                $table->string('notas', 500)->nullable();
                $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('grupo_familiar_alumno')) {
            Schema::create('grupo_familiar_alumno', function (Blueprint $table) {
                $table->id();
                $table->foreignId('grupo_familiar_id')->constrained('grupos_familiares')->cascadeOnDelete();
                $table->foreignId('alumno_id')->unique()->constrained('alumnos')->cascadeOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('grupo_familiar_alumno');
        Schema::dropIfExists('grupos_familiares');
    }
};
