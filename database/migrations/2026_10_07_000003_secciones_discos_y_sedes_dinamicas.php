<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las secciones «Discos» y «Sedes» del programa ahora muestran la discografía y el
 * mapa desde la base. Se quita del texto la lista fija, solo si la escuela no lo
 * editó (si lo cambió, queda como está y la lista se muestra igual arriba).
 */
return new class extends Migration
{
    private const DISCOS_ANTES = '<ul><li><strong>Percusión</strong> (1998)</li><li><strong>Viejos dioses</strong> (2001)</li><li><strong>Muñequitos del tambor</strong> (2004)</li><li><strong>Raíces</strong> (2007)</li><li><strong>Fantasma</strong> (2010)</li></ul><p>Podés escucharlos en YouTube o Spotify.</p>';

    private const DISCOS_DESPUES = '<p>Podés escucharlos en YouTube, Spotify y otras plataformas.</p>';

    private const SEDES_LISTA = '<ul><li><strong>Palomar</strong> — Ing. Marconi 181</li><li><strong>Saavedra</strong> — Ruiz Huidobro 4228</li><li><strong>Varela</strong> — Cerro Aconcagua 2153</li><li><strong>Quilmes</strong> — Humberto Primo 320</li><li><strong>Banfield</strong> — Av. Alsina 251</li><li><strong>Tacheles</strong> — Alsina 1475 (Congreso)</li></ul>';

    public function up(): void
    {
        if (! Schema::hasTable('programa_secciones')) {
            return;
        }
        DB::table('programa_secciones')->where('slug', 'discos')->where('cuerpo', self::DISCOS_ANTES)
            ->update(['cuerpo' => self::DISCOS_DESPUES]);

        $sedes = DB::table('programa_secciones')->where('slug', 'sedes-programa')->first();
        if ($sedes && str_starts_with((string) $sedes->cuerpo, self::SEDES_LISTA)) {
            DB::table('programa_secciones')->where('id', $sedes->id)
                ->update(['cuerpo' => substr((string) $sedes->cuerpo, strlen(self::SEDES_LISTA))]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('programa_secciones')) {
            return;
        }
        DB::table('programa_secciones')->where('slug', 'discos')->where('cuerpo', self::DISCOS_DESPUES)
            ->update(['cuerpo' => self::DISCOS_ANTES]);

        $sedes = DB::table('programa_secciones')->where('slug', 'sedes-programa')->first();
        if ($sedes && ! str_starts_with((string) $sedes->cuerpo, '<ul>')) {
            DB::table('programa_secciones')->where('id', $sedes->id)
                ->update(['cuerpo' => self::SEDES_LISTA.$sedes->cuerpo]);
        }
    }
};
