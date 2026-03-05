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
        if (Schema::hasTable('bloques')) {
            return;
        }
        Schema::create('bloques', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->integer('año'); // 1 a 6
            $table->foreignId('profesor_id')->nullable()->constrained('profesores')->nullOnDelete();
            $table->foreignId('sede_id')->constrained('sedes')->cascadeOnDelete();
            $table->integer('cantidad_max_alumnos')->default(20);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bloques');
    }
};
