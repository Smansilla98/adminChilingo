<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las claves que tocan plata dejan de borrar en cascada: borrar un alumno, una cuota o un
 * bloque con pagos o comprobantes lo rechaza la base (EliminacionSegura ya avisa antes con
 * un mensaje claro). Complementa 2026_10_27_000002, que lo hizo solo en MySQL para
 * pago_detalles; acá se suma SQLite y los comprobantes de cuota.
 *
 * MySQL: se busca el nombre real de cada clave (las bases viejas pueden tener otro) y se
 * cambia en dos ALTER. SQLite: el esquema de Laravel recrea la tabla con la regla nueva.
 */
return new class extends Migration
{
    /** tabla => [columna => tabla referida] */
    private const CLAVES = [
        'pago_detalles' => ['alumno_id' => 'alumnos', 'cuota_id' => 'cuotas'],
        'comprobantes_cuota_alumnos' => ['alumno_id' => 'alumnos'],
        'comprobante_cuota_alumno_items' => ['cuota_id' => 'cuotas', 'bloque_id' => 'bloques'],
    ];

    /** En MySQL, pago_detalles ya quedó en RESTRICT por la migración anterior: el down no lo toca. */
    private const YA_EN_MYSQL = ['pago_detalles'];

    public function up(): void
    {
        $this->aplicar('RESTRICT', false);
    }

    public function down(): void
    {
        $this->aplicar('CASCADE', true);
    }

    private function aplicar(string $regla, bool $bajando): void
    {
        $driver = DB::getDriverName();
        foreach (self::CLAVES as $tabla => $columnas) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }
            if ($driver === 'mysql' || $driver === 'mariadb') {
                if ($bajando && in_array($tabla, self::YA_EN_MYSQL, true)) {
                    continue;
                }
                $this->mysql($tabla, $columnas, $regla);
            } elseif ($driver === 'sqlite') {
                $this->sqlite($tabla, $columnas, $regla);
            }
        }
    }

    /**
     * @param  array<string, string>  $columnas
     */
    private function mysql(string $tabla, array $columnas, string $regla): void
    {
        $quitar = [];
        $agregar = [];
        foreach ($columnas as $columna => $referida) {
            $actual = DB::selectOne(
                'SELECT k.CONSTRAINT_NAME AS nombre, r.DELETE_RULE AS regla
                 FROM information_schema.KEY_COLUMN_USAGE k
                 JOIN information_schema.REFERENTIAL_CONSTRAINTS r
                   ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
                 WHERE k.TABLE_SCHEMA = DATABASE() AND k.TABLE_NAME = ? AND k.COLUMN_NAME = ? AND k.REFERENCED_TABLE_NAME = ?',
                [$tabla, $columna, $referida],
            );
            if ($actual === null || strtoupper((string) $actual->regla) === $regla) {
                continue;
            }
            $nombre = str_replace('`', '', (string) $actual->nombre);
            $quitar[] = "DROP FOREIGN KEY `{$nombre}`";
            $agregar[] = "ADD CONSTRAINT `{$nombre}` FOREIGN KEY (`{$columna}`) REFERENCES `{$referida}` (`id`) ON DELETE {$regla}";
        }
        if ($quitar !== []) {
            DB::statement("ALTER TABLE `{$tabla}` ".implode(', ', $quitar));
            DB::statement("ALTER TABLE `{$tabla}` ".implode(', ', $agregar));
        }
    }

    /**
     * @param  array<string, string>  $columnas
     */
    private function sqlite(string $tabla, array $columnas, string $regla): void
    {
        $actuales = collect(Schema::getForeignKeys($tabla));
        $cambiar = [];
        foreach ($columnas as $columna => $referida) {
            $fk = $actuales->first(fn (array $f) => $f['columns'] === [$columna] && $f['foreign_table'] === $referida);
            if ($fk && strtoupper((string) $fk['on_delete']) !== $regla) {
                $cambiar[$columna] = $referida;
            }
        }
        if ($cambiar === []) {
            return;
        }
        Schema::table($tabla, function (Blueprint $table) use ($cambiar, $regla) {
            foreach ($cambiar as $columna => $referida) {
                $table->dropForeign([$columna]);
                $table->foreign($columna)->references('id')->on($referida)->onDelete(strtolower($regla));
            }
        });
    }
};
