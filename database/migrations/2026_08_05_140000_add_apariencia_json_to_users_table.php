<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'apariencia_json')) {
                if (Schema::hasColumn('users', 'modulos_access')) {
                    $table->json('apariencia_json')->nullable()->after('modulos_access');
                } else {
                    $table->json('apariencia_json')->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'apariencia_json')) {
            return;
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('apariencia_json');
        });
    }
};
