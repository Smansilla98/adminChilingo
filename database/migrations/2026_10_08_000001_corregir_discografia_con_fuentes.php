<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Discografía: correcciones con fuente (investigación de prensa, octubre de 2026).
 * Solo cambia un campo si sigue igual a lo que se sembró: si la escuela ya lo editó,
 * queda como está. El `down` revierte con la misma regla.
 */
return new class extends Migration
{
    private const JSON = ['datos', 'temas', 'enlaces', 'fuentes'];

    public function up(): void
    {
        $this->aplicar(0, 1);
    }

    public function down(): void
    {
        $this->aplicar(1, 0);
    }

    private function aplicar(int $desde, int $hacia): void
    {
        if (! Schema::hasTable('discos')) {
            return;
        }
        foreach (require database_path('seeders/data/discos_correcciones_2026_10.php') as $slug => $campos) {
            $disco = DB::table('discos')->where('slug', $slug)->first();
            if (! $disco) {
                continue;
            }
            $cambios = [];
            foreach ($campos as $campo => $valores) {
                $actual = in_array($campo, self::JSON, true) ? json_decode((string) $disco->{$campo}, true) : $disco->{$campo};
                if ($actual === $valores[$desde]) {
                    $nuevo = $valores[$hacia];
                    $cambios[$campo] = in_array($campo, self::JSON, true) && $nuevo !== null
                        ? json_encode($nuevo, JSON_UNESCAPED_UNICODE)
                        : $nuevo;
                }
            }
            if ($cambios !== []) {
                DB::table('discos')->where('id', $disco->id)->update($cambios + ['updated_at' => now()]);
            }
        }
    }
};
