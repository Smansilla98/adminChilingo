<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('alumnos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_apellido');
            $table->string('dni')->unique();
            $table->date('fecha_nacimiento');
            $table->string('telefono')->nullable();
            $table->string('instrumento_principal');
            $table->string('instrumento_secundario')->nullable();
            $table->enum('tipo_tambor', ['Sede', 'Propio'])->default('Sede');
            $table->foreignId('bloque_id')->nullable()->constrained('bloques')->onDelete('set null');
            $table->foreignId('sede_id')->constrained('sedes')->onDelete('cascade');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alumnos');
    }
};
