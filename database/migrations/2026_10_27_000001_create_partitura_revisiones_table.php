<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pasajes de partitura marcados «Revisar:» que la escuela ya validó.
     * Se guardan aparte de la partitura para que la recarga de los JSON en cada
     * deploy (partituras:bootstrap --force) no los vuelva a marcar como pendientes.
     */
    public function up(): void
    {
        if (Schema::hasTable('partitura_revisiones')) {
            return;
        }

        Schema::create('partitura_revisiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programa_ritmo_id')->constrained('programa_ritmos')->cascadeOnDelete();
            $table->string('seccion', 120);
            $table->unsignedSmallInteger('compas');
            $table->string('nota', 500);
            $table->char('nota_hash', 32);
            $table->foreignId('validado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validado_at');
            $table->timestamps();
            $table->unique(['programa_ritmo_id', 'seccion', 'compas', 'nota_hash'], 'partitura_revisiones_marca_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partitura_revisiones');
    }
};
