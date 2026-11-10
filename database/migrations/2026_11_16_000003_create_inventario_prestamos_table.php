<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Préstamo de instrumentos: a quién, desde cuándo, hasta cuándo, seña y cómo volvió.
     * El estado (prestado / vencido / devuelto) se deduce de las fechas.
     */
    public function up(): void
    {
        Schema::create('inventario_prestamos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventario_item_id')->constrained('inventario_items')->cascadeOnDelete();
            $table->foreignId('alumno_id')->nullable()->constrained('alumnos')->nullOnDelete();
            $table->foreignId('persona_id')->nullable()->constrained('personas')->nullOnDelete();
            $table->string('responsable_nombre', 255)->nullable();
            $table->date('entregado_el');
            $table->date('devolucion_prevista');
            $table->date('devuelto_el')->nullable();
            $table->decimal('deposito', 12, 2)->nullable();
            $table->boolean('deposito_devuelto')->default(false);
            $table->string('condicion_entrega', 20)->nullable();
            $table->string('condicion_devolucion', 20)->nullable();
            $table->text('notas')->nullable();
            $table->foreignId('entregado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recibido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['inventario_item_id', 'devuelto_el']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_prestamos');
    }
};
