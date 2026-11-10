<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mensajes a grupos (difusión manual): a quiénes, por qué canales, cuándo y cómo resultó.
     * Los avisos en sí salen por NotificacionService (clave «difusion:{id}:{user}», sin duplicar).
     */
    public function up(): void
    {
        Schema::create('difusiones', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 120);
            $table->text('mensaje');
            $table->string('enlace', 255)->nullable();
            $table->json('canales');
            $table->json('filtros');
            $table->string('estado', 20)->default('programada')->index(); // programada|enviando|enviada|cancelada
            $table->timestamp('programada_para')->nullable()->index();
            $table->timestamp('enviada_at')->nullable();
            $table->unsignedInteger('destinatarios')->default(0);
            $table->json('resultado')->nullable(); // canal => cantidad enviada
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('difusiones');
    }
};
