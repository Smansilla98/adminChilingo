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

        Schema::table('programa_ritmos', function (Blueprint $table) {
            if (! Schema::hasColumn('programa_ritmos', 'medios')) {
                $table->json('medios')->nullable()->after('enlaces');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('programa_ritmos') && Schema::hasColumn('programa_ritmos', 'medios')) {
            Schema::table('programa_ritmos', function (Blueprint $table) {
                $table->dropColumn('medios');
            });
        }
    }
};
