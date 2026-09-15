<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('diseno_kit_assets')) {
            return;
        }

        Schema::create('diseno_kit_assets', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 120);
            $table->string('path');
            $table->string('mime', 80)->nullable();
            $table->unsignedInteger('bytes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diseno_kit_assets');
    }
};
