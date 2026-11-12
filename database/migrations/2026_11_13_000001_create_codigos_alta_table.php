<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Código de alta: 6 caracteres que la escuela le da a un alumno para que cree su cuenta
     * (segundo dato además de DNI y fecha de nacimiento). Se guarda solo el hash; vence en 7 días.
     */
    public function up(): void
    {
        Schema::create('codigos_alta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained('personas')->cascadeOnDelete();
            $table->string('codigo_hash', 64);
            $table->timestamp('vence_at');
            $table->timestamp('usado_at')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['persona_id', 'usado_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codigos_alta');
    }
};
