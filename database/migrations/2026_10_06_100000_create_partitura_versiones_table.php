<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Editor de ritmos: versiones publicadas y borrador autoguardado (docs/EDITOR_RITMOS.md §4).
 * No destructiva: `medios.partitura_score` sigue siendo lo publicado; la partitura actual de
 * cada toque se copia como versión 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('partitura_versiones')) {
            Schema::create('partitura_versiones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('programa_ritmo_id')->constrained('programa_ritmos')->cascadeOnDelete();
                $table->unsignedInteger('numero');
                $table->longText('score');
                $table->string('autor', 80)->nullable();
                $table->string('nota', 200)->nullable();
                $table->timestamps();

                $table->unique(['programa_ritmo_id', 'numero']);
            });
        }

        Schema::table('programa_ritmos', function (Blueprint $table) {
            if (! Schema::hasColumn('programa_ritmos', 'partitura_borrador')) {
                $table->longText('partitura_borrador')->nullable();
                $table->timestamp('partitura_borrador_at')->nullable();
                $table->string('partitura_borrador_autor', 80)->nullable();
            }
        });

        if (! Schema::hasColumn('programa_ritmos', 'medios')) {
            return;
        }
        DB::table('programa_ritmos')->select('id', 'medios')->orderBy('id')->chunkById(50, function ($filas) {
            foreach ($filas as $fila) {
                $medios = is_string($fila->medios) ? json_decode($fila->medios, true) : null;
                $score = is_array($medios) ? ($medios['partitura_score'] ?? null) : null;
                if (! is_array($score) || DB::table('partitura_versiones')->where('programa_ritmo_id', $fila->id)->exists()) {
                    continue;
                }
                $ultima = is_array($medios['partitura_ediciones'] ?? null) ? end($medios['partitura_ediciones']) : null;
                DB::table('partitura_versiones')->insert([
                    'programa_ritmo_id' => $fila->id,
                    'numero' => 1,
                    'score' => json_encode($score, JSON_UNESCAPED_UNICODE),
                    'autor' => is_array($ultima) ? mb_substr((string) ($ultima['nombre'] ?? ''), 0, 80) ?: null : null,
                    'nota' => 'Versión publicada antes del historial',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('programa_ritmos', function (Blueprint $table) {
            if (Schema::hasColumn('programa_ritmos', 'partitura_borrador')) {
                $table->dropColumn(['partitura_borrador', 'partitura_borrador_at', 'partitura_borrador_autor']);
            }
        });
        Schema::dropIfExists('partitura_versiones');
    }
};
