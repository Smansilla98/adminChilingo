<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shows', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->date('fecha');
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fin')->nullable();
            $table->string('lugar')->nullable();
            $table->text('descripcion')->nullable();
            $table->boolean('convocatoria_abierta')->default(false);
            $table->timestamps();
        });

        Schema::create('show_bloque', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->constrained('shows')->onDelete('cascade');
            $table->foreignId('bloque_id')->constrained('bloques')->onDelete('cascade');
            $table->timestamps();
            $table->unique(['show_id', 'bloque_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('show_bloque');
        Schema::dropIfExists('shows');
    }
};
