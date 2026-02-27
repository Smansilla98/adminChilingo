<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Ingreso por usuario y contraseña (sin correo).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 80)->nullable()->after('id');
        });
        User::all()->each(function (User $user) {
            $user->username = $user->email ?? ('user_' . $user->id);
            $user->save();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->unique('username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
