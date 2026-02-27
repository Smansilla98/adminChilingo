<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuotas (ej: Cuota Marzo 2025, Cuota Abril 2025).
     */
    public function up(): void
    {
        Schema::create('cuotas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre'); // ej. "Cuota Marzo 2025"
            $table->unsignedSmallInteger('año');
            $table->unsignedTinyInteger('mes')->nullable(); // 1-12, opcional
            $table->decimal('monto', 10, 2);
            $table->string('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuotas');
    }
};
