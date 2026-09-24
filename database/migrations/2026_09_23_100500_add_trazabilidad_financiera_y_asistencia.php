<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - Pagos: anulación en lugar de borrado.
 * - Gastos: circuito de aprobación (los existentes quedan aprobados).
 * - Asistencias: quién la tomó.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pagos')) {
            Schema::table('pagos', function (Blueprint $table) {
                if (! Schema::hasColumn('pagos', 'anulado_at')) {
                    $table->timestamp('anulado_at')->nullable();
                    $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
                    $table->string('motivo_anulacion', 500)->nullable();
                }
            });
        }

        if (Schema::hasTable('gastos') && ! Schema::hasColumn('gastos', 'estado')) {
            Schema::table('gastos', function (Blueprint $table) {
                $table->string('estado', 20)->default('aprobado'); // pendiente | aprobado | rechazado
                $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('aprobado_at')->nullable();
            });
        }

        if (Schema::hasTable('asistencias') && ! Schema::hasColumn('asistencias', 'registrado_por')) {
            Schema::table('asistencias', function (Blueprint $table) {
                $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pagos', 'anulado_at')) {
            Schema::table('pagos', function (Blueprint $table) {
                $table->dropConstrainedForeignId('anulado_por');
                $table->dropColumn(['anulado_at', 'motivo_anulacion']);
            });
        }
        if (Schema::hasColumn('gastos', 'estado')) {
            Schema::table('gastos', function (Blueprint $table) {
                $table->dropConstrainedForeignId('aprobado_por');
                $table->dropColumn(['estado', 'aprobado_at']);
            });
        }
        if (Schema::hasColumn('asistencias', 'registrado_por')) {
            Schema::table('asistencias', function (Blueprint $table) {
                $table->dropConstrainedForeignId('registrado_por');
            });
        }
    }
};
