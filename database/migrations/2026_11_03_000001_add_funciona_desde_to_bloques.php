<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Año en que el bloque empezó a funcionar. El «año» del programa (1° a 6°) queda como
     * referencia; con la antigüedad se puede evaluar en qué etapa está un bloque con muchos años.
     */
    public function up(): void
    {
        Schema::table('bloques', function (Blueprint $table) {
            $table->unsignedSmallInteger('funciona_desde')->nullable()->after('año');
        });
    }

    public function down(): void
    {
        Schema::table('bloques', function (Blueprint $table) {
            $table->dropColumn('funciona_desde');
        });
    }
};
