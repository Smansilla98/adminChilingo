<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ciclo lectivo (año, fechas) y reinscripción de cada alumno activo a ese ciclo. Solo se agregan
     * tablas: cuotas, pagos y asistencias existentes no cambian.
     */
    public function up(): void
    {
        Schema::create('ciclos_lectivos', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('anio')->unique();
            $table->string('nombre', 80);
            $table->date('inicio');
            $table->date('fin');
            $table->timestamp('cerrado_at')->nullable();
            $table->foreignId('abierto_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('reinscripciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ciclo_lectivo_id')->constrained('ciclos_lectivos')->cascadeOnDelete();
            $table->foreignId('alumno_id')->constrained('alumnos')->cascadeOnDelete();
            $table->string('estado', 16)->default('pendiente'); // pendiente | confirmada | baja
            $table->timestamp('confirmada_at')->nullable();
            $table->foreignId('resuelta_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resuelta_at')->nullable();
            $table->timestamps();
            $table->unique(['ciclo_lectivo_id', 'alumno_id']);
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reinscripciones');
        Schema::dropIfExists('ciclos_lectivos');
    }
};
