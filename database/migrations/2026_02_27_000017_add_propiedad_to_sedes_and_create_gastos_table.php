<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sedes', function (Blueprint $table) {
            $table->string('tipo_propiedad', 20)->default('alquilada')->after('direccion'); // propia, alquilada, compartida, otro
            $table->decimal('costo_alquiler_mensual', 12, 2)->nullable()->after('tipo_propiedad');
        });

        Schema::create('gastos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sede_id')->nullable()->constrained('sedes')->onDelete('set null');
            $table->foreignId('bloque_id')->nullable()->constrained('bloques')->onDelete('set null');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');

            $table->date('fecha');
            $table->string('tipo', 30); // sueldo, alquiler, servicio, reparacion, insumo, servicio_externo, otro
            $table->string('subtipo', 40)->nullable(); // luz, agua, electricista, plomero, tambores, edilicio, etc.

            $table->string('descripcion')->nullable();
            $table->decimal('monto', 14, 2);
            $table->string('proveedor')->nullable();

            $table->text('notas')->nullable();

            $table->timestamps();

            $table->index(['sede_id', 'tipo', 'subtipo']);
            $table->index(['fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gastos');

        Schema::table('sedes', function (Blueprint $table) {
            $table->dropColumn(['tipo_propiedad', 'costo_alquiler_mensual']);
        });
    }
};

