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
        Schema::table('sedes', function (Blueprint $table) {
            $table->foreignId('coordinador_id')->nullable()->after('direccion')->constrained('profesores')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('sedes', function (Blueprint $table) {
            $table->dropForeign(['coordinador_id']);
        });
    }
};
