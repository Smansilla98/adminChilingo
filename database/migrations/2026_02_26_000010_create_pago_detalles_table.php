<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Detalle: qué alumno pagó qué cuota en este pago (trazabilidad).
     */
    public function up(): void
    {
        if (Schema::hasTable('pago_detalles')) {
            return;
        }
        Schema::create('pago_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')->constrained('pagos')->cascadeOnDelete();
            $table->foreignId('alumno_id')->constrained('alumnos')->cascadeOnDelete();
            $table->foreignId('cuota_id')->constrained('cuotas')->cascadeOnDelete();
            $table->decimal('monto', 10, 2);
            $table->timestamps();

            $table->unique(['pago_id', 'alumno_id', 'cuota_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pago_detalles');
    }
};
