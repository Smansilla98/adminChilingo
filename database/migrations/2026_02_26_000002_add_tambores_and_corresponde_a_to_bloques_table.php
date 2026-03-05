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
        if (!Schema::hasTable('bloques')) {
            return;
        }
        $add = [];
        if (!Schema::hasColumn('bloques', 'tambores')) {
            $add[] = 'tambores';
        }
        if (!Schema::hasColumn('bloques', 'corresponde_a')) {
            $add[] = 'corresponde_a';
        }
        if (empty($add)) {
            return;
        }
        Schema::table('bloques', function (Blueprint $table) use ($add) {
            if (in_array('tambores', $add)) {
                $table->json('tambores')->nullable()->after('cantidad_max_alumnos');
            }
            if (in_array('corresponde_a', $add)) {
                $table->string('corresponde_a')->nullable()->after('profesor_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('bloques')) {
            return;
        }
        $drop = array_filter(['tambores', 'corresponde_a'], fn ($c) => Schema::hasColumn('bloques', $c));
        if (!empty($drop)) {
            Schema::table('bloques', function (Blueprint $table) use ($drop) {
                $table->dropColumn($drop);
            });
        }
    }
};
