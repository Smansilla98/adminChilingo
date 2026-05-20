<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('profesor_sede')) {
            return;
        }

        Schema::create('profesor_sede', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profesor_id')->constrained('profesores')->cascadeOnDelete();
            $table->foreignId('sede_id')->constrained('sedes')->cascadeOnDelete();
            $table->string('rol', 32);
            $table->timestamps();
            $table->unique(['profesor_id', 'sede_id', 'rol']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profesor_sede');
    }
};
