<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Preinscripción pública con lista de espera: la administración aprueba (crea el alumno) o rechaza. */
    public function up(): void
    {
        Schema::create('preinscripciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->string('apellido', 120);
            $table->string('dni', 20)->nullable();
            $table->date('fecha_nacimiento');
            $table->string('telefono', 30);
            $table->string('email', 255)->nullable();
            $table->foreignId('sede_id')->constrained('sedes')->restrictOnDelete();
            $table->foreignId('bloque_id')->nullable()->constrained('bloques')->nullOnDelete();
            $table->string('tutor_nombre', 120)->nullable();
            $table->string('tutor_dni', 20)->nullable();
            $table->string('tutor_telefono', 30)->nullable();
            $table->string('tutor_vinculo', 40)->nullable();
            $table->text('notas')->nullable();
            $table->string('estado', 16)->default('pendiente')->index(); // pendiente | aprobada | rechazada
            $table->string('motivo_rechazo', 255)->nullable();
            $table->foreignId('alumno_id')->nullable()->constrained('alumnos')->nullOnDelete();
            $table->foreignId('resuelto_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resuelto_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preinscripciones');
    }
};
