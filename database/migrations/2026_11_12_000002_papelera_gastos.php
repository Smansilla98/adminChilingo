<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gastos y gastos de la gira: borrar los manda a la papelera (con quién y por qué) en
 * lugar de borrarlos de verdad. Se pueden restaurar.
 */
return new class extends Migration
{
    private const TABLAS = ['gastos', 'villa_gesell_gastos'];

    public function up(): void
    {
        foreach (self::TABLAS as $tabla) {
            if (! Schema::hasTable($tabla) || Schema::hasColumn($tabla, 'deleted_at')) {
                continue;
            }
            Schema::table($tabla, function (Blueprint $table) {
                $table->softDeletes();
                $table->foreignId('eliminado_por')->nullable()->constrained('users')->nullOnDelete();
                $table->string('motivo_eliminacion', 500)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLAS as $tabla) {
            if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, 'deleted_at')) {
                continue;
            }
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropConstrainedForeignId('eliminado_por');
            });
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropColumn(['deleted_at', 'motivo_eliminacion']);
            });
        }
    }
};
