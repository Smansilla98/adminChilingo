<?php

namespace App\Domain\Datos;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Backup de la base en SQL comprimido, sin depender de mysqldump (la imagen no lo trae).
 *
 * Se guarda en el disco persistente (`comprobantes`, el volumen de Railway) bajo
 * `respaldos/`, junto a `respaldos/ultimo.json` con el resultado del último intento,
 * que lee la pantalla Estado del sistema.
 *
 * Si están RESPALDO_DISCO_EXTERNO y RESPALDO_CLAVE (config/operacion.php), además sube una
 * copia cifrada (`.sql.gz.enc`, ver CifradoRespaldo) a ese disco (S3 / Cloudflare R2) y
 * conserva las últimas RESPALDO_EXTERNO_CONSERVAR. Sin clave nunca sube nada sin cifrar.
 */
class Respaldo
{
    public const CARPETA = 'respaldos';

    public const MARCA = 'respaldos/ultimo.json';

    private const FILAS_POR_INSERT = 200;

    /**
     * @return array{archivo: string, bytes: int, tablas: int, filas: int, segundos: float, externo: array{ok: bool, archivo?: string, error?: string}|null}
     */
    public function generar(int $conservar = 14): array
    {
        $inicio = microtime(true);
        $temporal = tempnam(sys_get_temp_dir(), 'respaldo-');
        $gz = gzopen($temporal, 'wb6');
        if ($gz === false) {
            throw new RuntimeException('No se pudo crear el archivo temporal del backup.');
        }

        try {
            [$tablas, $filas] = $this->volcar($gz);
        } catch (Throwable $e) {
            gzclose($gz);
            @unlink($temporal);
            $this->marcar(['ok' => false, 'error' => $e->getMessage()]);
            throw $e;
        }
        gzclose($gz);

        $archivo = self::CARPETA.'/chilinga-'.now()->format('Ymd-His').'.sql.gz';
        $stream = fopen($temporal, 'rb');
        $guardado = Storage::disk('comprobantes')->writeStream($archivo, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
        $bytes = (int) filesize($temporal);

        if (! $guardado) {
            @unlink($temporal);
            $this->marcar(['ok' => false, 'error' => 'No se pudo escribir en el disco persistente.']);
            throw new RuntimeException('No se pudo escribir el backup en el disco persistente.');
        }

        // La copia externa nunca hace fallar el backup local: su resultado queda en la marca.
        $externo = self::externoConfigurado() ? $this->subirExterno($temporal, basename($archivo)) : null;
        @unlink($temporal);

        $this->podar($conservar);
        $resultado = [
            'archivo' => $archivo,
            'bytes' => $bytes,
            'tablas' => $tablas,
            'filas' => $filas,
            'segundos' => round(microtime(true) - $inicio, 1),
            'externo' => $externo,
        ];
        $this->marcar(['ok' => true] + $resultado);

        return $resultado;
    }

    /** Hay disco externo y clave: cada backup sube también una copia cifrada. */
    public static function externoConfigurado(): bool
    {
        return filled(config('operacion.respaldo.disco_externo')) && filled(config('operacion.respaldo.clave'));
    }

    /** @return list<string> copias cifradas en el disco externo, de la más nueva a la más vieja */
    public static function archivosExternos(): array
    {
        $disco = Storage::disk((string) config('operacion.respaldo.disco_externo'));
        $lista = array_filter(
            $disco->files((string) config('operacion.respaldo.carpeta_externa', self::CARPETA)),
            fn (string $f) => str_ends_with($f, '.sql.gz.enc'),
        );
        rsort($lista);

        return $lista;
    }

    /**
     * @return array{ok: bool, archivo?: string, error?: string}
     */
    private function subirExterno(string $temporal, string $nombre): array
    {
        $cifrado = $temporal.'.enc';
        try {
            CifradoRespaldo::cifrar($temporal, $cifrado, (string) config('operacion.respaldo.clave'));
            $destino = trim((string) config('operacion.respaldo.carpeta_externa', self::CARPETA), '/').'/'.$nombre.'.enc';
            $disco = Storage::disk((string) config('operacion.respaldo.disco_externo'));
            $stream = fopen($cifrado, 'rb');
            $ok = $disco->writeStream($destino, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
            if (! $ok) {
                throw new RuntimeException('el disco externo rechazó el archivo');
            }
            foreach (array_slice(self::archivosExternos(), max(1, (int) config('operacion.respaldo.conservar_externo', 30))) as $viejo) {
                $disco->delete($viejo);
            }

            return ['ok' => true, 'archivo' => $destino];
        } catch (Throwable $e) {
            report($e);

            return ['ok' => false, 'error' => 'No se pudo subir la copia externa: '.$e->getMessage()];
        } finally {
            @unlink($cifrado);
        }
    }

    /**
     * @return array{ok: bool, fecha: string, archivo?: string, bytes?: int, error?: string, externo?: array{ok: bool, archivo?: string, error?: string}|null}|null
     */
    public static function ultimo(): ?array
    {
        try {
            $json = Storage::disk('comprobantes')->get(self::MARCA);
        } catch (Throwable) {
            return null;
        }
        $datos = $json ? json_decode($json, true) : null;

        return is_array($datos) ? $datos : null;
    }

    /** @return list<string> backups guardados, del más nuevo al más viejo */
    public static function archivos(): array
    {
        $lista = array_filter(
            Storage::disk('comprobantes')->files(self::CARPETA),
            fn (string $f) => str_ends_with($f, '.sql.gz'),
        );
        rsort($lista);

        return $lista;
    }

    /**
     * @param  resource  $gz
     * @return array{0: int, 1: int}
     */
    private function volcar($gz): array
    {
        $conexion = DB::connection();
        $pdo = $conexion->getPdo();
        $driver = $conexion->getDriverName();
        $tablas = $this->tablas($driver);

        gzwrite($gz, '-- Backup La Chilinga · '.now()->toIso8601String()." · {$driver}\n");
        if ($driver === 'mysql') {
            gzwrite($gz, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
            // Sin buffer: las tablas grandes se leen de a una fila, sin cargar todo en memoria.
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        } elseif ($driver === 'sqlite') {
            // Las tablas se vuelcan en orden alfabético: sin esto, restaurar choca con las claves foráneas.
            gzwrite($gz, "PRAGMA foreign_keys=OFF;\n\n");
        }

        $filasTotal = 0;
        try {
            foreach ($tablas as $tabla) {
                $q = $this->quoteId($tabla, $driver);
                gzwrite($gz, "DROP TABLE IF EXISTS {$q};\n".$this->createTable($tabla, $driver).";\n");

                $stmt = $pdo->query("SELECT * FROM {$q}");
                $lote = [];
                $columnas = null;
                while (($fila = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
                    $columnas ??= implode(', ', array_map(fn ($c) => $this->quoteId($c, $driver), array_keys($fila)));
                    $lote[] = '('.implode(', ', array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $fila)).')';
                    if (count($lote) >= self::FILAS_POR_INSERT) {
                        gzwrite($gz, "INSERT INTO {$q} ({$columnas}) VALUES\n".implode(",\n", $lote).";\n");
                        $filasTotal += count($lote);
                        $lote = [];
                    }
                }
                $stmt->closeCursor();
                if ($lote !== []) {
                    gzwrite($gz, "INSERT INTO {$q} ({$columnas}) VALUES\n".implode(",\n", $lote).";\n");
                    $filasTotal += count($lote);
                }
                gzwrite($gz, "\n");
            }
        } finally {
            if ($driver === 'mysql') {
                $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
            }
        }

        if ($driver === 'mysql') {
            gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
        }

        return [count($tablas), $filasTotal];
    }

    /** @return list<string> */
    private function tablas(string $driver): array
    {
        if ($driver === 'sqlite') {
            return array_map(fn ($r) => $r->name, DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"));
        }

        return array_map(fn ($r) => array_values((array) $r)[0], DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"));
    }

    private function createTable(string $tabla, string $driver): string
    {
        if ($driver === 'sqlite') {
            return (string) DB::selectOne('SELECT sql FROM sqlite_master WHERE type = ? AND name = ?', ['table', $tabla])->sql;
        }
        $fila = (array) DB::selectOne('SHOW CREATE TABLE '.$this->quoteId($tabla, $driver));

        return (string) ($fila['Create Table'] ?? array_values($fila)[1]);
    }

    private function quoteId(string $nombre, string $driver): string
    {
        return $driver === 'mysql'
            ? '`'.str_replace('`', '``', $nombre).'`'
            : '"'.str_replace('"', '""', $nombre).'"';
    }

    private function podar(int $conservar): void
    {
        foreach (array_slice(self::archivos(), max(1, $conservar)) as $viejo) {
            Storage::disk('comprobantes')->delete($viejo);
        }
    }

    private function marcar(array $datos): void
    {
        try {
            Storage::disk('comprobantes')->put(self::MARCA, json_encode(['fecha' => now()->toIso8601String()] + $datos, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        } catch (Throwable) {
            // Si ni la marca se puede escribir, Estado del sistema lo muestra como «sin backups».
        }
    }
}
