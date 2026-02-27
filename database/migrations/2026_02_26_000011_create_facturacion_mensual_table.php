<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Facturación por mes (resumen: cantidad alumnxs, facturado, etc.).
     */
    public function up(): void
    {
        Schema::create('facturacion_mensual', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sede_id')->nullable()->constrained('sedes')->onDelete('cascade');
            $table->unsignedSmallInteger('año');
            $table->unsignedTinyInteger('mes'); // 1-12
            $table->unsignedInteger('cantidad_alumnos')->default(0);
            $table->decimal('monto_facturado', 12, 2)->default(0);
            $table->decimal('monto_previsto', 12, 2)->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->unique(['sede_id', 'año', 'mes']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facturacion_mensual');
    }
};
