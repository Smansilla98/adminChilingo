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
        if (!Schema::hasTable('cuotas') || Schema::hasColumn('cuotas', 'fecha_vencimiento')) {
            return;
        }

        Schema::table('cuotas', function (Blueprint $table) {
            $table->date('fecha_vencimiento')->nullable()->after('mes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('cuotas') || !Schema::hasColumn('cuotas', 'fecha_vencimiento')) {
            return;
        }

        Schema::table('cuotas', function (Blueprint $table) {
            $table->dropColumn('fecha_vencimiento');
        });
    }
};
