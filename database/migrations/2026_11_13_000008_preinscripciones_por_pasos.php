<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Preinscripción por pasos: la persona la empieza (estado en_curso, con un token para seguir desde
     * el mismo link) y los datos llegan en varios pasos. Fecha de nacimiento y teléfono pasan a
     * opcionales porque se piden en pasos posteriores.
     */
    public function up(): void
    {
        Schema::table('preinscripciones', function (Blueprint $table) {
            $table->string('token', 64)->nullable()->unique()->after('id');
            $table->unsignedTinyInteger('paso')->default(0)->after('estado');
            $table->date('fecha_nacimiento')->nullable()->change();
            $table->string('telefono', 30)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('preinscripciones', function (Blueprint $table) {
            $table->dropUnique(['token']);
            $table->dropColumn(['token', 'paso']);
        });
    }
};
