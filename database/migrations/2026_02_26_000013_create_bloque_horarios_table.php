<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Días y horarios de cada bloque (ej. Lunes 18:00-19:30).
     */
    public function up(): void
    {
        Schema::create('bloque_horarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bloque_id')->constrained('bloques')->onDelete('cascade');
            $table->unsignedTinyInteger('dia_semana'); // 1=lunes ... 7=domingo
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->timestamps();
            $table->unique(['bloque_id', 'dia_semana', 'hora_inicio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bloque_horarios');
    }
};
