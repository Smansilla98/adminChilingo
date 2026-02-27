<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Programa oficial de la escuela: toques/ritmos por año (1° a 6°).
     */
    public function up(): void
    {
        Schema::create('programa_ritmos', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('año'); // 1 a 6
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('nombre');
            $table->string('autor')->nullable(); // D. Buira, Adaptación, etc.
            $table->boolean('opcional')->default(false); // Opcional 1er o 2do año, etc.
            $table->string('notas')->nullable(); // "Ritmo Popular Brasil", etc.
            $table->timestamps();
            $table->index(['año', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programa_ritmos');
    }
};
