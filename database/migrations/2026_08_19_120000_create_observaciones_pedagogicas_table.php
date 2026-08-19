<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('observaciones_pedagogicas')) {
            return;
        }

        Schema::create('observaciones_pedagogicas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')->constrained('alumnos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('bloque_id')->nullable()->constrained('bloques')->nullOnDelete();
            $table->date('fecha');
            $table->string('tipo', 32);
            $table->string('toque', 160)->nullable();
            $table->text('cuerpo');
            $table->timestamps();

            $table->index(['alumno_id', 'fecha']);
            $table->index(['bloque_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observaciones_pedagogicas');
    }
};
