<?php

use Database\Seeders\ProgramaRitmosSeeder;
use Database\Seeders\ProgramaSeccionesSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('programa_secciones')) {
            Schema::create('programa_secciones', function (Blueprint $table) {
                $table->id();
                $table->string('slug')->unique();
                $table->string('titulo');
                $table->string('subtitulo')->nullable();
                $table->longText('cuerpo')->nullable();
                $table->unsignedSmallInteger('orden')->default(0);
                $table->string('categoria', 32)->default('institucional');
                $table->unsignedTinyInteger('anio')->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->index(['categoria', 'orden']);
            });
        }

        if (Schema::hasTable('programa_ritmos')) {
            Schema::table('programa_ritmos', function (Blueprint $table) {
                if (! Schema::hasColumn('programa_ritmos', 'slug')) {
                    $table->string('slug')->nullable()->unique()->after('id');
                }
                if (! Schema::hasColumn('programa_ritmos', 'resumen')) {
                    $table->text('resumen')->nullable()->after('notas');
                }
                if (! Schema::hasColumn('programa_ritmos', 'contenido')) {
                    $table->longText('contenido')->nullable()->after('resumen');
                }
                if (! Schema::hasColumn('programa_ritmos', 'secciones')) {
                    $table->json('secciones')->nullable()->after('contenido');
                }
                if (! Schema::hasColumn('programa_ritmos', 'enlaces')) {
                    $table->json('enlaces')->nullable()->after('secciones');
                }
                if (! Schema::hasColumn('programa_ritmos', 'publicado')) {
                    $table->boolean('publicado')->default(true)->after('enlaces');
                }
            });
        }

        if (Schema::hasTable('programa_secciones')) {
            ProgramaSeccionesSeeder::poblarSiVacio();
        }
        if (Schema::hasTable('programa_ritmos') && Schema::hasColumn('programa_ritmos', 'slug')) {
            ProgramaRitmosSeeder::asegurarSlugs();
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('programa_ritmos')) {
            Schema::table('programa_ritmos', function (Blueprint $table) {
                foreach (['slug', 'resumen', 'contenido', 'secciones', 'enlaces', 'publicado'] as $col) {
                    if (Schema::hasColumn('programa_ritmos', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
        Schema::dropIfExists('programa_secciones');
    }
};
