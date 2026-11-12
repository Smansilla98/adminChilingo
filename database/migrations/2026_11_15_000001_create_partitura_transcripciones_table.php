<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pedidos de transcripción automática (grabación → borrador de partitura).
     * El resultado crudo del servicio queda guardado para revisar y para entrenar.
     */
    public function up(): void
    {
        if (Schema::hasTable('partitura_transcripciones')) {
            return;
        }

        Schema::create('partitura_transcripciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programa_ritmo_id')->constrained('programa_ritmos')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('estado', 20)->default('pendiente')->index();
            $table->unsignedTinyInteger('progreso')->default(0);
            $table->string('audio_path', 255);
            $table->json('resultado')->nullable();
            $table->json('borrador')->nullable();
            $table->string('error', 500)->nullable();
            $table->string('autor', 80)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partitura_transcripciones');
    }
};
