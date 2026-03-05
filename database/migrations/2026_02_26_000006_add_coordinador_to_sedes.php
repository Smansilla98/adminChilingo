<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un profesor puede ser coordinador de una sede (ej: Banfield).
     */
    public function up(): void
    {
        if (!Schema::hasTable('sedes') || Schema::hasColumn('sedes', 'coordinador_id')) {
            return;
        }
        try {
            Schema::table('sedes', function (Blueprint $table) {
                $table->foreignId('coordinador_id')->nullable()->after('direccion')->constrained('profesores')->nullOnDelete();
            });
        } catch (\Throwable $e) {
            Schema::table('sedes', function (Blueprint $table) {
                $table->unsignedBigInteger('coordinador_id')->nullable()->after('direccion');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('sedes') || !Schema::hasColumn('sedes', 'coordinador_id')) {
            return;
        }
        Schema::table('sedes', function (Blueprint $table) {
            try {
                $table->dropForeign(['coordinador_id']);
            } catch (\Throwable $e) {
                // No había FK
            }
            $table->dropColumn('coordinador_id');
        });
    }
};
