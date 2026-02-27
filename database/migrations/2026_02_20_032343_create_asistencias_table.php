<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('asistencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')->constrained('alumnos')->onDelete('cascade');
            $table->foreignId('bloque_id')->constrained('bloques')->onDelete('cascade');
            $table->date('fecha');
            $table->boolean('presente')->default(true);
            $table->timestamps();
            
            // Evitar duplicados
            $table->unique(['alumno_id', 'bloque_id', 'fecha']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asistencias');
    }
};
