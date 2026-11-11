<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Códigos de recuperación de contraseña: se guarda solo el hash, vencen en 15 min y tienen 5 intentos. */
    public function up(): void
    {
        Schema::create('recuperaciones_clave', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('canal', 16); // email | whatsapp
            $table->string('codigo_hash', 64);
            $table->timestamp('vence_at');
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->timestamp('usado_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'usado_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recuperaciones_clave');
    }
};
