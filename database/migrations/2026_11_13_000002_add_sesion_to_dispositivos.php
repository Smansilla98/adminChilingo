<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sesión de la app (token de Sanctum) con la que se registró cada celular para avisos push:
     * al cerrar esa sesión se borra el celular y no le llegan avisos de la cuenta anterior.
     */
    public function up(): void
    {
        Schema::table('dispositivos', function (Blueprint $table) {
            $table->unsignedBigInteger('personal_access_token_id')->nullable()->after('user_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('dispositivos', function (Blueprint $table) {
            $table->dropIndex(['personal_access_token_id']);
            $table->dropColumn('personal_access_token_id');
        });
    }
};
