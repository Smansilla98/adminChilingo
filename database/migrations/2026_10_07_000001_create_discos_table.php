<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Discografía de la banda de La Chilinga (pública en /programa/discografia). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discos', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('titulo');
            $table->string('titulo_alternativo')->nullable();
            $table->unsignedSmallInteger('anio');
            $table->string('nota_anio', 255)->nullable();
            $table->string('color', 7)->default('#c2410c');
            $table->text('descripcion')->nullable();
            $table->json('datos')->nullable();
            $table->json('temas')->nullable();
            $table->string('nota_temas', 255)->nullable();
            $table->json('enlaces')->nullable();
            $table->json('fuentes')->nullable();
            $table->string('portada_path')->nullable();
            $table->boolean('publicado')->default(true);
            $table->timestamps();

            $table->index(['publicado', 'anio']);
        });

        $ahora = now();
        foreach (require database_path('seeders/data/discos_chilinga.php') as $disco) {
            DB::table('discos')->insert(array_merge($disco, [
                'datos' => json_encode($disco['datos'], JSON_UNESCAPED_UNICODE),
                'temas' => json_encode($disco['temas'], JSON_UNESCAPED_UNICODE),
                'enlaces' => json_encode($disco['enlaces'], JSON_UNESCAPED_UNICODE),
                'fuentes' => json_encode($disco['fuentes'], JSON_UNESCAPED_UNICODE),
                'publicado' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('discos');
    }
};
