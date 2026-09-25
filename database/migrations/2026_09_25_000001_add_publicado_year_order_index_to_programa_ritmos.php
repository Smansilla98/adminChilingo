<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('programa_ritmos')) {
            return;
        }

        Schema::table('programa_ritmos', function (Blueprint $table): void {
            $table->index(['publicado', 'año', 'orden'], 'programa_ritmos_publicado_anio_orden_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('programa_ritmos')) {
            return;
        }

        Schema::table('programa_ritmos', function (Blueprint $table): void {
            $table->dropIndex('programa_ritmos_publicado_anio_orden_index');
        });
    }
};
