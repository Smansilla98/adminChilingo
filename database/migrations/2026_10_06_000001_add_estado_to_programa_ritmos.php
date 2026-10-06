<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estado de cada toque dentro del programa:
 * - vigente: la escuela lo sigue tocando (si no, queda como referencia).
 * - en_programa: forma parte del programa oficial (si no, se retiró).
 * - nombres_anteriores: historial de renombres, para buscarlo por su nombre viejo.
 * - estado_nota: aclaración breve ("se dejó de tocar en 2024", "pasó a 3°").
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('programa_ritmos')) {
            return;
        }

        Schema::table('programa_ritmos', function (Blueprint $table): void {
            $table->boolean('vigente')->default(true)->after('opcional');
            $table->boolean('en_programa')->default(true)->after('vigente');
            $table->json('nombres_anteriores')->nullable()->after('nombre');
            $table->string('estado_nota', 500)->nullable()->after('en_programa');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('programa_ritmos')) {
            return;
        }

        Schema::table('programa_ritmos', function (Blueprint $table): void {
            $table->dropColumn(['vigente', 'en_programa', 'nombres_anteriores', 'estado_nota']);
        });
    }
};
