<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Borra la clave legacy medios.partitura_vexflow (compositor v2/v3).
 * El editor nuevo guarda en medios.partitura_score (modelo v4).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('programa_ritmos') || ! Schema::hasColumn('programa_ritmos', 'medios')) {
            return;
        }

        DB::table('programa_ritmos')->select('id', 'medios')->orderBy('id')->chunk(100, function ($filas) {
            foreach ($filas as $fila) {
                $medios = json_decode((string) $fila->medios, true);
                if (! is_array($medios) || ! array_key_exists('partitura_vexflow', $medios)) {
                    continue;
                }
                unset($medios['partitura_vexflow']);
                DB::table('programa_ritmos')->where('id', $fila->id)->update([
                    'medios' => json_encode($medios, JSON_UNESCAPED_UNICODE),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Irreversible: el compositor v3 fue reemplazado por el editor v4.
    }
};
