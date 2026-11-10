<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Progreso del alumno por toque del programa: en qué anda con cada toque, los repasos
 * con devolución, las propuestas de paso de año y el historial de años cursados.
 * Idempotente: crea solo lo que falta.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('progreso_toques')) {
            Schema::create('progreso_toques', function (Blueprint $table) {
                $table->id();
                $table->foreignId('alumno_id')->constrained('alumnos')->cascadeOnDelete();
                $table->foreignId('programa_ritmo_id')->constrained('programa_ritmos')->cascadeOnDelete();
                $table->string('estado', 20)->default('no_empezado');
                $table->date('fecha')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('bloque_id')->nullable()->constrained('bloques')->nullOnDelete();
                $table->string('nota', 400)->nullable();
                $table->timestamps();

                $table->unique(['alumno_id', 'programa_ritmo_id']);
                $table->index(['programa_ritmo_id', 'estado']);
            });
        }

        if (! Schema::hasTable('evaluaciones_toque')) {
            Schema::create('evaluaciones_toque', function (Blueprint $table) {
                $table->id();
                $table->foreignId('alumno_id')->constrained('alumnos')->cascadeOnDelete();
                $table->foreignId('bloque_id')->nullable()->constrained('bloques')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->date('fecha');
                $table->json('toques');
                $table->string('resultado', 20);
                $table->text('devolucion')->nullable();
                $table->boolean('visible_alumno')->default(true);
                $table->timestamps();

                $table->index(['alumno_id', 'fecha']);
            });
        }

        if (! Schema::hasTable('alumno_anios')) {
            Schema::create('alumno_anios', function (Blueprint $table) {
                $table->id();
                $table->foreignId('alumno_id')->constrained('alumnos')->cascadeOnDelete();
                $table->unsignedTinyInteger('anio');
                $table->foreignId('bloque_id')->nullable()->constrained('bloques')->nullOnDelete();
                $table->date('desde');
                $table->date('hasta')->nullable();
                $table->foreignId('confirmado_por')->nullable()->constrained('users')->nullOnDelete();
                $table->string('nota', 400)->nullable();
                $table->timestamps();

                $table->index(['alumno_id', 'hasta']);
            });
        }

        if (! Schema::hasTable('promociones_anio')) {
            Schema::create('promociones_anio', function (Blueprint $table) {
                $table->id();
                $table->foreignId('alumno_id')->constrained('alumnos')->cascadeOnDelete();
                $table->unsignedTinyInteger('anio_desde');
                $table->unsignedTinyInteger('anio_hasta');
                $table->string('estado', 20)->default('propuesta');
                $table->foreignId('resuelto_por')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resuelto_at')->nullable();
                $table->string('nota', 400)->nullable();
                $table->timestamps();

                $table->index(['alumno_id', 'estado']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('promociones_anio');
        Schema::dropIfExists('alumno_anios');
        Schema::dropIfExists('evaluaciones_toque');
        Schema::dropIfExists('progreso_toques');
    }
};
