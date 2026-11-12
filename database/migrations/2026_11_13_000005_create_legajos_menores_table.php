<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Legajo de un menor: tutor responsable, autorización de uso de imagen y apto médico (archivo privado). */
    public function up(): void
    {
        Schema::create('legajos_menores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->unique()->constrained('personas')->cascadeOnDelete();
            $table->string('tutor_nombre', 120)->nullable();
            $table->string('tutor_dni', 20)->nullable();
            $table->string('tutor_telefono', 30)->nullable();
            $table->string('tutor_vinculo', 40)->nullable();
            $table->boolean('uso_imagen')->nullable(); // sí / no / sin responder
            $table->date('uso_imagen_fecha')->nullable();
            $table->string('apto_medico_path', 255)->nullable();
            $table->string('apto_medico_nombre', 255)->nullable();
            $table->date('apto_medico_vence')->nullable()->index();
            $table->date('apto_aviso_enviado_el')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legajos_menores');
    }
};
