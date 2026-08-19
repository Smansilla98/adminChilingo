<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('comprobantes_cuota_alumnos')) {
            return;
        }
        if (Schema::hasColumn('comprobantes_cuota_alumnos', 'cargado_por_user_id')) {
            return;
        }
        Schema::table('comprobantes_cuota_alumnos', function (Blueprint $table) {
            $table->foreignId('cargado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('comprobantes_cuota_alumnos') || ! Schema::hasColumn('comprobantes_cuota_alumnos', 'cargado_por_user_id')) {
            return;
        }
        Schema::table('comprobantes_cuota_alumnos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cargado_por_user_id');
        });
    }
};
