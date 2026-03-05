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
        if (!Schema::hasTable('eventos') || Schema::hasColumn('eventos', 'cantidad_personas')) {
            return;
        }
        Schema::table('eventos', function (Blueprint $table) {
            $table->unsignedInteger('cantidad_personas')->nullable()->after('bloque_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('eventos') || !Schema::hasColumn('eventos', 'cantidad_personas')) {
            return;
        }
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn('cantidad_personas');
        });
    }
};
