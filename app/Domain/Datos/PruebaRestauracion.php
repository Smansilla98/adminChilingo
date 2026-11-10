<?php

namespace App\Domain\Datos;

use Generator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Restaura un backup (.sql.gz de Respaldo) en una base TEMPORAL y la revisa: nunca toca la base real.
 * Sirve para comprobar que los backups se pueden usar de verdad (ver docs/ROLLBACK.md).
 *
 * MySQL: crea `<base>_prueba_<aleatorio>` en el mismo servidor (necesita permiso CREATE DATABASE).
 * SQLite: usa un archivo temporal.
 */
class PruebaRestauracion
{
    private const CONEXION = 'restauracion_prueba';

    private const ADMIN = 'restauracion_admin';

    /**
     * @param  string  $sqlGz  ruta local a un .sql.gz
     * @return array{base: string, sentencias: int, tablas_esperadas: int, tablas: int, filas: int, migraciones: int, conservada: bool}
     */
    public function probar(string $sqlGz, bool $conservarBase = false): array
    {
        $principal = DB::connection();
        $driver = $principal->getDriverName();
        $origen = $this->driverDelBackup($sqlGz);
        if ($origen !== $driver) {
            throw new RuntimeException("El backup es de {$origen} y la base actual es {$driver}: probalo en un entorno {$origen}.");
        }

        $config = (array) config('database.connections.'.$principal->getName());
        // CREATE/DROP DATABASE van por una conexión aparte: en MySQL hacen commit implícito y no
        // deben cerrar transacciones abiertas en la conexión principal.
        config(['database.connections.'.self::ADMIN => $config]);
        DB::purge(self::ADMIN);
        $admin = DB::connection(self::ADMIN);
        if ($driver === 'mysql') {
            $base = Str::limit((string) $config['database'], 40, '').'_prueba_'.Str::lower(Str::random(6));
            $base = preg_replace('/[^A-Za-z0-9_]/', '_', $base);
            $admin->statement("CREATE DATABASE `{$base}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $config['database'] = $base;
        } elseif ($driver === 'sqlite') {
            $base = (string) tempnam(sys_get_temp_dir(), 'restauracion-').'.sqlite';
            touch($base);
            $config['database'] = $base;
        } else {
            throw new RuntimeException("Motor no soportado: {$driver}.");
        }

        config(['database.connections.'.self::CONEXION => $config]);
        DB::purge(self::CONEXION);

        try {
            $conexion = DB::connection(self::CONEXION);
            $sentencias = 0;
            $esperadas = 0;
            foreach ($this->sentencias($sqlGz, $driver) as $sql) {
                if (preg_match('/^CREATE TABLE/i', $sql)) {
                    $esperadas++;
                }
                $conexion->unprepared($sql);
                $sentencias++;
            }

            $tablas = $driver === 'sqlite'
                ? array_map(fn ($r) => $r->name, $conexion->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"))
                : array_map(fn ($r) => array_values((array) $r)[0], $conexion->select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"));
            $filas = 0;
            foreach ($tablas as $tabla) {
                $filas += (int) $conexion->table($tabla)->count();
            }
            $migraciones = in_array('migrations', $tablas, true) ? (int) $conexion->table('migrations')->count() : 0;

            if (count($tablas) < $esperadas) {
                throw new RuntimeException('Se restauraron '.count($tablas)." de {$esperadas} tablas.");
            }
            if ($migraciones === 0) {
                throw new RuntimeException('La base restaurada no tiene la tabla de migraciones con datos.');
            }

            return [
                'base' => $base,
                'sentencias' => $sentencias,
                'tablas_esperadas' => $esperadas,
                'tablas' => count($tablas),
                'filas' => $filas,
                'migraciones' => $migraciones,
                'conservada' => $conservarBase,
            ];
        } finally {
            DB::purge(self::CONEXION);
            if (! $conservarBase) {
                try {
                    if ($driver === 'mysql') {
                        $admin->statement("DROP DATABASE IF EXISTS `{$base}`");
                    } else {
                        @unlink($base);
                    }
                } catch (Throwable) {
                    // Si no se pudo borrar, queda a mano: el comando muestra el nombre.
                }
            }
            DB::purge(self::ADMIN);
        }
    }

    private function driverDelBackup(string $sqlGz): string
    {
        $gz = @gzopen($sqlGz, 'rb');
        if ($gz === false) {
            throw new RuntimeException('No se pudo abrir el backup.');
        }
        $primera = (string) gzgets($gz, 4096);
        gzclose($gz);
        if (! preg_match('/^-- Backup La Chilinga .* · (\w+)\s*$/u', $primera, $m)) {
            throw new RuntimeException('El archivo no parece un backup de La Chilinga (falta la cabecera).');
        }

        return $m[1];
    }

    /**
     * Separa el volcado en sentencias. Una sentencia termina en una línea que acaba en «;» fuera de
     * una cadena entre comillas simples (en MySQL las barras invertidas escapan; en SQLite no).
     *
     * @return Generator<int, string>
     */
    public function sentencias(string $sqlGz, string $driver): Generator
    {
        $gz = gzopen($sqlGz, 'rb');
        if ($gz === false) {
            throw new RuntimeException('No se pudo abrir el backup.');
        }
        try {
            $actual = '';
            $enCadena = false;
            while (($linea = gzgets($gz)) !== false) {
                if (! $enCadena && $actual === '' && (trim($linea) === '' || str_starts_with($linea, '--'))) {
                    continue;
                }
                $actual .= $linea;
                $sinEscapes = $driver === 'mysql' ? (string) preg_replace('/\\\\./s', '', $linea) : $linea;
                if (substr_count($sinEscapes, "'") % 2 === 1) {
                    $enCadena = ! $enCadena;
                }
                if (! $enCadena && str_ends_with(rtrim($linea), ';')) {
                    yield rtrim(rtrim($actual), ';');
                    $actual = '';
                }
            }
            if (trim($actual) !== '') {
                throw new RuntimeException('El backup termina a la mitad de una sentencia: está incompleto.');
            }
        } finally {
            gzclose($gz);
        }
    }
}
