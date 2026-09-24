<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vincula perfiles (alumno, profesor) y cuentas (users) con su persona.
 * Columnas nullable: el backfill las completa; nada se borra.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['alumnos', 'profesores', 'users'] as $tabla) {
            if (! Schema::hasTable($tabla) || Schema::hasColumn($tabla, 'persona_id')) {
                continue;
            }
            Schema::table($tabla, function (Blueprint $table) {
                $table->foreignId('persona_id')->nullable()->after('id')->constrained('personas')->nullOnDelete();
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'activo')) {
                $table->boolean('activo')->default(true)->after('password');
            }
            if (! Schema::hasColumn('users', 'ultimo_acceso_at')) {
                $table->timestamp('ultimo_acceso_at')->nullable()->after('activo');
            }
        });
    }

    public function down(): void
    {
        foreach (['alumnos', 'profesores', 'users'] as $tabla) {
            if (Schema::hasTable($tabla) && Schema::hasColumn($tabla, 'persona_id')) {
                Schema::table($tabla, function (Blueprint $table) {
                    $table->dropConstrainedForeignId('persona_id');
                });
            }
        }
        Schema::table('users', function (Blueprint $table) {
            foreach (['activo', 'ultimo_acceso_at'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
