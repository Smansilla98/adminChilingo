<?php

namespace App\Domain\Datos;

use RuntimeException;

/**
 * Cifrado de la copia externa del backup, compatible con la línea de comandos de OpenSSL:
 *
 *   openssl enc -d -aes-256-cbc -pbkdf2 -iter 100000 -md sha256 -pass env:RESPALDO_CLAVE \
 *       -in chilinga-....sql.gz.enc | gunzip > chilinga.sql
 *
 * Formato: "Salted__" + sal (8 bytes) + AES-256-CBC con PKCS#7; clave e IV salen de
 * PBKDF2-SHA256(clave, sal, 100000). Se procesa por bloques: no carga el backup en memoria.
 */
class CifradoRespaldo
{
    public const CIFRADO = 'aes-256-cbc';

    public const ITERACIONES = 100000;

    private const MAGIA = 'Salted__';

    private const TROZO = 1048576; // 1 MiB, múltiplo de 16

    public static function cifrar(string $origen, string $destino, string $clave): void
    {
        self::exigirClave($clave);
        [$in, $out] = self::abrir($origen, $destino);
        try {
            $sal = random_bytes(8);
            [$k, $iv] = self::derivar($clave, $sal);
            fwrite($out, self::MAGIA.$sal);

            $pendiente = '';
            while (! feof($in)) {
                $pendiente .= (string) fread($in, self::TROZO);
                $largo = strlen($pendiente) - (strlen($pendiente) % 16);
                // Siempre queda al menos un bloque para el padding final.
                if ($largo > 0 && $largo === strlen($pendiente)) {
                    $largo -= 16;
                }
                if ($largo > 0) {
                    $bloque = self::aes(true, substr($pendiente, 0, $largo), $k, $iv, false);
                    $iv = substr($bloque, -16);
                    fwrite($out, $bloque);
                    $pendiente = substr($pendiente, $largo);
                }
            }
            fwrite($out, self::aes(true, $pendiente, $k, $iv, true));
        } finally {
            fclose($in);
            fclose($out);
        }
    }

    public static function descifrar(string $origen, string $destino, string $clave): void
    {
        self::exigirClave($clave);
        [$in, $out] = self::abrir($origen, $destino);
        try {
            $cabecera = (string) fread($in, 16);
            if (strlen($cabecera) !== 16 || ! str_starts_with($cabecera, self::MAGIA)) {
                throw new RuntimeException('El archivo no es un backup cifrado válido.');
            }
            [$k, $iv] = self::derivar($clave, substr($cabecera, 8, 8));

            $pendiente = '';
            while (! feof($in)) {
                $pendiente .= (string) fread($in, self::TROZO);
                // El último bloque se guarda para quitarle el padding al final.
                $largo = strlen($pendiente) - (strlen($pendiente) % 16) - 16;
                if ($largo > 0) {
                    $parte = substr($pendiente, 0, $largo);
                    fwrite($out, self::aes(false, $parte, $k, $iv, false));
                    $iv = substr($parte, -16);
                    $pendiente = substr($pendiente, $largo);
                }
            }
            if ($pendiente === '' || strlen($pendiente) % 16 !== 0) {
                throw new RuntimeException('El backup cifrado está incompleto.');
            }
            fwrite($out, self::aes(false, $pendiente, $k, $iv, true));
        } finally {
            fclose($in);
            fclose($out);
        }
    }

    /** @return array{0: string, 1: string} clave AES e IV */
    private static function derivar(string $clave, string $sal): array
    {
        $material = hash_pbkdf2('sha256', $clave, $sal, self::ITERACIONES, 48, true);

        return [substr($material, 0, 32), substr($material, 32, 16)];
    }

    private static function aes(bool $cifrar, string $datos, string $k, string $iv, bool $padding): string
    {
        $opciones = OPENSSL_RAW_DATA | ($padding ? 0 : OPENSSL_ZERO_PADDING);
        $r = $cifrar
            ? openssl_encrypt($datos, self::CIFRADO, $k, $opciones, $iv)
            : openssl_decrypt($datos, self::CIFRADO, $k, $opciones, $iv);
        if ($r === false) {
            throw new RuntimeException($cifrar ? 'No se pudo cifrar el backup.' : 'No se pudo descifrar el backup: la clave no coincide o el archivo está dañado.');
        }

        return $r;
    }

    /** @return array{0: resource, 1: resource} */
    private static function abrir(string $origen, string $destino): array
    {
        $in = @fopen($origen, 'rb');
        if ($in === false) {
            throw new RuntimeException("No se pudo leer {$origen}.");
        }
        $out = @fopen($destino, 'wb');
        if ($out === false) {
            fclose($in);
            throw new RuntimeException("No se pudo escribir {$destino}.");
        }

        return [$in, $out];
    }

    private static function exigirClave(string $clave): void
    {
        if (strlen($clave) < 16) {
            throw new RuntimeException('RESPALDO_CLAVE tiene que tener al menos 16 caracteres.');
        }
    }
}
