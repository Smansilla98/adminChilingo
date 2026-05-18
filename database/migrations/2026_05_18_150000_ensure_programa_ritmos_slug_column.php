<?php

use Database\Seeders\ProgramaRitmosSeeder;
use Database\Seeders\ProgramaSeccionesSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seguridad en deploy: si 120000 corrió antes que existiera la columna slug, la agrega acá.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('programa_ritmos')) {
            return;
        }

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

        if (Schema::hasTable('programa_secciones')) {
            ProgramaSeccionesSeeder::poblarSiVacio();
        }

        ProgramaRitmosSeeder::asegurarSlugs();
    }

    public function down(): void
    {
        //
    }
};
