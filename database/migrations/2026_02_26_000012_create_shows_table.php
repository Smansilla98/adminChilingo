<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shows')) {
            return;
        }
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

        if (!Schema::hasTable('show_bloque')) {
            Schema::create('show_bloque', function (Blueprint $table) {
                $table->id();
                $table->foreignId('show_id')->constrained('shows')->cascadeOnDelete();
                $table->foreignId('bloque_id')->constrained('bloques')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['show_id', 'bloque_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('show_bloque');
        Schema::dropIfExists('shows');
    }
};
