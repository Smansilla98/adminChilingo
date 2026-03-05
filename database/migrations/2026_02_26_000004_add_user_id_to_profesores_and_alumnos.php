<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un usuario puede ser profesor y/o alumno (la misma persona en ambos roles).
     */
    public function up(): void
    {
        if (Schema::hasTable('profesores') && !Schema::hasColumn('profesores', 'user_id')) {
            Schema::table('profesores', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->onDelete('set null');
            });
        }

        if (Schema::hasTable('alumnos') && !Schema::hasColumn('alumnos', 'user_id')) {
            Schema::table('alumnos', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::table('profesores', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        Schema::table('alumnos', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
    }
};
