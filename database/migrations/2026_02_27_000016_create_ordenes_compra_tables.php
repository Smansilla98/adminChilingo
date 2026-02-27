<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordenes_compra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sede_id')->constrained('sedes')->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');

            $table->string('motivo', 30)->default('reposicion'); // reposicion, nuevos_talleres, nuevos_alumnos, mixto, otro
            $table->string('estado', 20)->default('borrador'); // borrador, enviada, aprobada, recibida, cancelada
            $table->date('fecha_objetivo')->nullable();

            $table->text('justificacion')->nullable();
            $table->decimal('total_estimado', 14, 2)->default(0);

            $table->timestamps();

            $table->index(['sede_id', 'estado']);
        });

        Schema::create('orden_compra_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_compra_id')->constrained('ordenes_compra')->onDelete('cascade');

            $table->string('tipo', 30)->nullable(); // instrumento, parche, repuesto, otro
            $table->string('familia', 50)->nullable(); // repique, surdo medio, etc.
            $table->string('descripcion'); // libre, con características

            $table->string('marca')->nullable();
            $table->string('modelo')->nullable();
            $table->string('medida')->nullable();

            $table->decimal('cantidad', 10, 2)->default(1);
            $table->string('unidad', 20)->nullable(); // u, pares, mts, etc.

            $table->decimal('precio_estimado', 14, 2)->nullable();
            $table->decimal('subtotal_estimado', 14, 2)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orden_compra_items');
        Schema::dropIfExists('ordenes_compra');
    }
};

