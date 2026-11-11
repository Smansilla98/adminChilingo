<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liquidación docente por horas: valor hora y modalidad por docente (y sede),
 * ajustes manuales de horas y la liquidación mensual con su estado.
 * Convive con el abono por porcentaje de sede (PagosDocenteService), que no se toca.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('docente_tarifas')) {
            Schema::create('docente_tarifas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('profesor_id')->constrained('profesores')->cascadeOnDelete();
                // null = valor general del docente (y su modalidad); con sede = valor hora para esa sede.
                $table->foreignId('sede_id')->nullable()->constrained('sedes')->cascadeOnDelete();
                $table->string('modalidad', 20)->default('hora');
                $table->decimal('valor_hora', 12, 2)->default(0);
                $table->timestamps();

                $table->unique(['profesor_id', 'sede_id']);
            });
        }

        if (! Schema::hasTable('docente_horas_ajustes')) {
            Schema::create('docente_horas_ajustes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('profesor_id')->constrained('profesores')->cascadeOnDelete();
                $table->foreignId('bloque_id')->nullable()->constrained('bloques')->nullOnDelete();
                $table->foreignId('sede_id')->nullable()->constrained('sedes')->nullOnDelete();
                $table->date('fecha');
                $table->string('tipo', 20);
                $table->decimal('horas', 6, 2);
                $table->string('motivo', 255)->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['profesor_id', 'fecha']);
            });
        }

        if (! Schema::hasTable('liquidaciones_docentes')) {
            Schema::create('liquidaciones_docentes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('profesor_id')->constrained('profesores')->restrictOnDelete();
                $table->unsignedSmallInteger('anio');
                $table->unsignedTinyInteger('mes');
                $table->string('modalidad', 20);
                $table->decimal('horas_dictadas', 8, 2)->default(0);
                $table->decimal('horas_ajuste', 8, 2)->default(0);
                $table->decimal('monto_horas', 12, 2)->default(0);
                $table->decimal('monto_porcentaje', 12, 2)->default(0);
                $table->decimal('monto_total', 12, 2)->default(0);
                $table->string('estado', 20)->default('borrador');
                $table->json('detalle')->nullable();
                $table->text('observaciones')->nullable();
                $table->foreignId('generada_por')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('aprobada_por')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('aprobada_at')->nullable();
                $table->foreignId('pagada_por')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('pagada_at')->nullable();
                $table->json('gasto_ids')->nullable();
                $table->timestamps();

                $table->unique(['profesor_id', 'anio', 'mes']);
                $table->index(['anio', 'mes', 'estado']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidaciones_docentes');
        Schema::dropIfExists('docente_horas_ajustes');
        Schema::dropIfExists('docente_tarifas');
    }
};
