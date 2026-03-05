<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un usuario puede ser profesor y/o alumno (la misma persona en ambos roles).
     * Si la tabla users no existe o MySQL no puede crear la FK, se agrega solo la columna.
     */
    public function up(): void
    {
        $addColumnWithFk = function (string $tableName) {
            if (!Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'user_id')) {
                return;
            }
            try {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->onDelete('set null');
                });
            } catch (\Throwable $e) {
                if (!Schema::hasColumn($tableName, 'user_id')) {
                    Schema::table($tableName, function (Blueprint $table) {
                        $table->unsignedBigInteger('user_id')->nullable()->after('id');
                    });
                }
            }
        };

        $addColumnWithFk('profesores');
        $addColumnWithFk('alumnos');
    }

    public function down(): void
    {
        foreach (['profesores', 'alumnos'] as $tableName) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'user_id')) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                try {
                    $table->dropForeign(['user_id']);
                } catch (\Throwable $e) {
                    // No había FK (se añadió solo la columna)
                }
                $table->dropColumn('user_id');
            });
        }
    }
};
