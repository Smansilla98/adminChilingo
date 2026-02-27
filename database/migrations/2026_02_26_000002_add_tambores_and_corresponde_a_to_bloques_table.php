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
        Schema::table('bloques', function (Blueprint $table) {
            // Tambores del bloque (ej: Repique, Medio, Redoblante, Fondo Agudo, Fondo Grave)
            $table->json('tambores')->nullable()->after('cantidad_max_alumnos');
            // A quién corresponde el bloque (responsable / destinatario)
            $table->string('corresponde_a')->nullable()->after('profesor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bloques', function (Blueprint $table) {
            $table->dropColumn(['tambores', 'corresponde_a']);
        });
    }
};
