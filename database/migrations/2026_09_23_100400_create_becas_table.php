<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('becas')) {
            return;
        }

        Schema::create('becas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')->constrained('alumnos')->cascadeOnDelete();
            $table->string('tipo', 20);                 // porcentaje | monto_fijo | total
            $table->decimal('porcentaje', 5, 2)->nullable();
            $table->decimal('monto', 12, 2)->nullable(); // descuento fijo por cuota
            // Opcional: limitar la beca a un bloque o sede (si el alumno cursa en varios).
            $table->foreignId('bloque_id')->nullable()->constrained('bloques')->nullOnDelete();
            $table->foreignId('sede_id')->nullable()->constrained('sedes')->nullOnDelete();
            $table->string('motivo')->nullable();
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->string('estado', 20)->default('activa'); // activa | suspendida | finalizada
            $table->text('observaciones')->nullable();
            $table->foreignId('otorgada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['alumno_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('becas');
    }
};
