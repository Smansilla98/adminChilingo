<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inventario por sede (instrumentos, herramientas, repuestos, etc).
     *
     * - Soporta items únicos (ej. un tambor) y consumibles (cantidad/unidad).
     * - Permite propiedad de escuela o de alumno (para instrumentos propios).
     */
    public function up(): void
    {
        Schema::create('inventario_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sede_id')->constrained('sedes')->onDelete('cascade');

            // Tipo / categoría general del inventario
            $table->string('tipo', 30); // instrumento, herramienta, accesorio, repuesto, parche, tela, masa, etc.

            // Identificación principal
            $table->string('nombre'); // ej: "Repique 12\"", "Surdo medio 16\"", "Llave de afinación"
            $table->string('codigo')->nullable(); // etiqueta interna (opcional)

            // Para consumibles (parches, telas, repuestos, etc.)
            $table->boolean('es_consumible')->default(false);
            $table->decimal('cantidad', 10, 2)->default(1);
            $table->string('unidad', 20)->nullable(); // u, pares, mts, kg, etc.

            // Propiedad / trazabilidad de dueño
            $table->string('propietario_tipo', 20)->default('escuela'); // escuela | alumno
            $table->foreignId('alumno_id')->nullable()->constrained('alumnos')->onDelete('set null');

            // Características (estilo ficha MercadoLibre)
            $table->string('marca')->nullable();
            $table->string('modelo')->nullable();
            $table->string('linea')->nullable();
            $table->string('material')->nullable();
            $table->string('color')->nullable();
            $table->string('medida')->nullable(); // libre (ej: 12", 16", 14", etc.)
            $table->decimal('diametro_pulgadas', 5, 2)->nullable();
            $table->unsignedSmallInteger('torres')->nullable();
            $table->unsignedSmallInteger('anio_fabricacion')->nullable();

            // Compra/donación/reparación/estado
            $table->string('origen_adquisicion', 20)->nullable(); // comprado | donado | prestado | otro
            $table->date('fecha_adquisicion')->nullable();
            $table->decimal('precio', 12, 2)->nullable();
            $table->string('estado', 20)->default('bueno'); // nuevo | bueno | regular | reparacion | baja
            $table->date('reparado_en')->nullable();
            $table->text('detalle_reparacion')->nullable();

            // Flags útiles
            $table->boolean('utilitario')->default(false); // si es insumo de repuesto / utilitario
            $table->text('notas')->nullable();

            $table->timestamps();

            $table->index(['sede_id', 'tipo']);
            $table->index(['propietario_tipo', 'alumno_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_items');
    }
};

