<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alcance: general (toda la escuela), sede (cuota diferencial por sede), bloque (legado).
     */
    public function up(): void
    {
        if (! Schema::hasTable('cuotas')) {
            return;
        }
        Schema::table('cuotas', function (Blueprint $table) {
            if (! Schema::hasColumn('cuotas', 'alcance')) {
                $table->string('alcance', 20)->default('bloque')->after('bloque_id');
            }
            if (! Schema::hasColumn('cuotas', 'sede_id')) {
                $table->foreignId('sede_id')->nullable()->after('alcance')->constrained('sedes')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cuotas')) {
            return;
        }
        Schema::table('cuotas', function (Blueprint $table) {
            if (Schema::hasColumn('cuotas', 'sede_id')) {
                $table->dropForeign(['sede_id']);
            }
            if (Schema::hasColumn('cuotas', 'sede_id')) {
                $table->dropColumn('sede_id');
            }
            if (Schema::hasColumn('cuotas', 'alcance')) {
                $table->dropColumn('alcance');
            }
        });
    }
};
