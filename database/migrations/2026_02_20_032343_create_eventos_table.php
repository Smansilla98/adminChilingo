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
        Schema::create('eventos', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->date('fecha');
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fin')->nullable();
            $table->foreignId('sede_id')->nullable()->constrained('sedes')->onDelete('set null');
            $table->enum('tipo_evento', ['show', 'taller', 'muestra', 'gira'])->default('taller');
            $table->foreignId('profesor_id')->nullable()->constrained('profesores')->onDelete('set null');
            $table->foreignId('bloque_id')->nullable()->constrained('bloques')->onDelete('set null');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eventos');
    }
};
