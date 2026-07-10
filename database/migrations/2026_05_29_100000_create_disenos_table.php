<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('disenos')) {
            Schema::create('disenos', function (Blueprint $table) {
                $table->id();
                $table->string('titulo');
                $table->string('formato', 40)->default('flyer_feed');
                $table->unsignedInteger('ancho')->default(1080);
                $table->unsignedInteger('alto')->default(1350);
                $table->json('canvas_json')->nullable();
                $table->string('preview_path')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('disenos');
    }
};
