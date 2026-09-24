<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Páginas de un diseño (editor OpenDesign: un diseño puede tener varias páginas).
 * Los diseños existentes se abren con una página creada desde su canvas_json.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('diseno_paginas')) {
            return;
        }

        Schema::create('diseno_paginas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diseno_id')->constrained('disenos')->cascadeOnDelete();
            $table->string('titulo', 120)->default('Página 1');
            $table->longText('canvas_json')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['diseno_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diseno_paginas');
    }
};
