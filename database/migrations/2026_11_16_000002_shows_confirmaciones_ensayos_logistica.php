<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shows: confirmación de cada alumno convocado (y su asistencia), lista de temas,
     * ensayos con asistencia propia, logística del traslado e instrumentos que van.
     */
    public function up(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->string('punto_encuentro', 255)->nullable()->after('lugar');
            $table->time('hora_encuentro')->nullable()->after('punto_encuentro');
            $table->string('transporte', 255)->nullable()->after('hora_encuentro');
            $table->text('logistica_notas')->nullable()->after('transporte');
        });

        Schema::create('show_confirmaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->constrained('shows')->cascadeOnDelete();
            $table->foreignId('alumno_id')->constrained('alumnos')->cascadeOnDelete();
            $table->string('respuesta', 10)->nullable(); // voy|no_voy|tal_vez
            $table->string('comentario', 255)->nullable();
            $table->timestamp('respondido_at')->nullable();
            $table->boolean('asistio')->nullable();
            $table->timestamps();
            $table->unique(['show_id', 'alumno_id']);
        });

        Schema::create('show_temas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->constrained('shows')->cascadeOnDelete();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->foreignId('programa_ritmo_id')->nullable()->constrained('programa_ritmos')->nullOnDelete();
            $table->string('titulo', 255)->nullable();
            $table->string('notas', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('show_ensayos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->constrained('shows')->cascadeOnDelete();
            $table->date('fecha');
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fin')->nullable();
            $table->string('lugar', 255)->nullable();
            $table->string('notas', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('show_ensayo_asistencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_ensayo_id')->constrained('show_ensayos')->cascadeOnDelete();
            $table->foreignId('alumno_id')->constrained('alumnos')->cascadeOnDelete();
            $table->boolean('presente')->default(true);
            $table->timestamps();
            $table->unique(['show_ensayo_id', 'alumno_id']);
        });

        Schema::create('show_instrumentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->constrained('shows')->cascadeOnDelete();
            $table->foreignId('inventario_item_id')->nullable()->constrained('inventario_items')->nullOnDelete();
            $table->string('descripcion', 255)->nullable();
            $table->unsignedSmallInteger('cantidad')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('show_instrumentos');
        Schema::dropIfExists('show_ensayo_asistencias');
        Schema::dropIfExists('show_ensayos');
        Schema::dropIfExists('show_temas');
        Schema::dropIfExists('show_confirmaciones');
        Schema::table('shows', function (Blueprint $table) {
            $table->dropColumn(['punto_encuentro', 'hora_encuentro', 'transporte', 'logistica_notas']);
        });
    }
};
