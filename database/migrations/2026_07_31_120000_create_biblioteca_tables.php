<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('biblioteca_tags')) {
            Schema::create('biblioteca_tags', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 80);
                $table->string('slug', 80)->unique();
                $table->unsignedInteger('usos')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('biblioteca_items')) {
            Schema::create('biblioteca_items', function (Blueprint $table) {
                $table->id();
                $table->string('titulo', 180);
                $table->text('descripcion')->nullable();
                $table->string('tipo', 20); // imagen, video, audio, pdf, enlace, otro
                $table->string('path')->nullable();
                $table->string('url', 500)->nullable();
                $table->string('mime', 120)->nullable();
                $table->string('nombre_original', 255)->nullable();
                $table->unsignedBigInteger('bytes')->nullable();
                $table->string('autor_nombre', 120)->nullable();
                $table->string('estado', 20)->default('publicado'); // publicado, oculto
                $table->string('ip', 45)->nullable();
                $table->timestamps();

                $table->index(['estado', 'created_at']);
                $table->index('tipo');
            });
        }

        if (! Schema::hasTable('biblioteca_item_tag')) {
            Schema::create('biblioteca_item_tag', function (Blueprint $table) {
                $table->foreignId('biblioteca_item_id')->constrained('biblioteca_items')->cascadeOnDelete();
                $table->foreignId('biblioteca_tag_id')->constrained('biblioteca_tags')->cascadeOnDelete();
                $table->primary(['biblioteca_item_id', 'biblioteca_tag_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('biblioteca_item_tag');
        Schema::dropIfExists('biblioteca_items');
        Schema::dropIfExists('biblioteca_tags');
    }
};
