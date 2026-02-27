<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Coordinadores de área: género, costa, tambores.
     * Un profesor puede ser coordinador de una o más áreas.
     */
    public function up(): void
    {
        Schema::create('coordinador_area', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profesor_id')->constrained('profesores')->onDelete('cascade');
            $table->string('area', 50)->comment('género, costa, tambores');
            $table->timestamps();

            $table->unique(['profesor_id', 'area']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coordinador_area');
    }
};
