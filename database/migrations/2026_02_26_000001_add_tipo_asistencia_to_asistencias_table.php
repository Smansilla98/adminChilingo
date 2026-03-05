<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('asistencias') || Schema::hasColumn('asistencias', 'tipo_asistencia')) {
            return;
        }
        Schema::table('asistencias', function (Blueprint $table) {
            $table->string('tipo_asistencia', 50)->default('presente')->after('fecha');
        });

        if (Schema::hasColumn('asistencias', 'presente')) {
            \DB::table('asistencias')->where('presente', true)->update(['tipo_asistencia' => 'presente']);
            \DB::table('asistencias')->where('presente', false)->update(['tipo_asistencia' => 'ausente']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('asistencias') || !Schema::hasColumn('asistencias', 'tipo_asistencia')) {
            return;
        }
        Schema::table('asistencias', function (Blueprint $table) {
            $table->dropColumn('tipo_asistencia');
        });
    }
};
