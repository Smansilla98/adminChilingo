<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identidad única: una persona puede ser alumna, docente, coordinadora, etc.
 * Aditiva: no toca datos existentes (el backfill está en una migración posterior).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('personas')) {
            return;
        }

        Schema::create('personas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('apellido')->nullable();
            // Sin unique en DB: los datos legacy pueden traer duplicados; la app valida altas nuevas
            // y `chilinga:diagnose` reporta los existentes.
            $table->string('dni', 20)->nullable()->index();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('telefono', 40)->nullable();
            $table->string('email')->nullable()->index();
            $table->string('direccion')->nullable();
            $table->string('contacto_emergencia_nombre')->nullable();
            $table->string('contacto_emergencia_telefono', 40)->nullable();
            $table->string('foto_path')->nullable();
            $table->string('estado', 20)->default('activo')->index(); // activo | inactivo | baja
            $table->text('observaciones')->nullable();
            // Si la persona se fusionó en otra, apunta a la persona que quedó.
            $table->foreignId('fusionada_en_id')->nullable()->constrained('personas')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['apellido', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personas');
    }
};
