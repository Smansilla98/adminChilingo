<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Comprobantes de cuota enviados por alumnos (sin login), visibles para profes/admin.
     */
    public function up(): void
    {
        if (! Schema::hasTable('comprobantes_cuota_alumnos')) {
            Schema::create('comprobantes_cuota_alumnos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('alumno_id')->constrained('alumnos')->cascadeOnDelete();
                $table->foreignId('sede_id')->nullable()->constrained('sedes')->nullOnDelete();
                $table->date('fecha_pago');
                $table->decimal('monto_total', 12, 2);
                $table->string('comprobante_path')->nullable();
                $table->string('notas', 1000)->nullable();
                $table->string('estado', 24)->default('pendiente'); // pendiente, visto
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('comprobante_cuota_alumno_items')) {
            Schema::create('comprobante_cuota_alumno_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('comprobante_cuota_alumno_id')
                    ->constrained('comprobantes_cuota_alumnos')
                    ->cascadeOnDelete();
                $table->foreignId('cuota_id')->constrained('cuotas')->cascadeOnDelete();
                $table->foreignId('bloque_id')->constrained('bloques')->cascadeOnDelete();
                $table->decimal('monto', 12, 2);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobante_cuota_alumno_items');
        Schema::dropIfExists('comprobantes_cuota_alumnos');
    }
};
