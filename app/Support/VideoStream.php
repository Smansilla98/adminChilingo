<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Entrega un video con HTTP Range (206) para que el navegador y la app puedan saltar en el tiempo.
 * Laravel no lo trae de fábrica. Si el disco no es local, se entrega el archivo entero (200).
 */
final class VideoStream
{
    private const TIPO = [
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mov' => 'video/quicktime',
    ];

    public static function responder(Request $request, string $disco, string $path, string $nombre): Response
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $tipo = self::TIPO[$ext] ?? 'application/octet-stream';
        $cabeceras = [
            'Content-Type' => $tipo,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, max-age=86400',
            'Content-Disposition' => 'inline; filename="'.str_replace(['"', '\\', "\r", "\n"], '', $nombre).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "sandbox; default-src 'none'; media-src 'self'",
        ];

        $ruta = self::rutaLocal($disco, $path);
        if ($ruta === null) {
            return self::completo($disco, $path, $cabeceras);
        }
        $tamano = (int) filesize($ruta);
        $rango = self::rango((string) $request->header('Range'), $tamano);
        if ($rango === false) {
            return response('', 416, ['Content-Range' => 'bytes */'.$tamano] + $cabeceras);
        }
        if ($rango === null) {
            return response()->file($ruta, $cabeceras);
        }

        [$desde, $hasta] = $rango;
        $largo = $hasta - $desde + 1;
        $cabeceras['Content-Length'] = (string) $largo;
        $cabeceras['Content-Range'] = "bytes {$desde}-{$hasta}/{$tamano}";

        return new StreamedResponse(function () use ($ruta, $desde, $largo): void {
            $fh = fopen($ruta, 'rb');
            if ($fh === false) {
                return;
            }
            fseek($fh, $desde);
            $pendiente = $largo;
            while ($pendiente > 0 && ! feof($fh)) {
                $bloque = fread($fh, min(65536, $pendiente));
                if ($bloque === false || $bloque === '') {
                    break;
                }
                echo $bloque;
                $pendiente -= strlen($bloque);
                flush();
            }
            fclose($fh);
        }, 206, $cabeceras);
    }

    /** Ruta del archivo si el disco es local; null si no (p. ej. almacenamiento remoto). */
    private static function rutaLocal(string $disco, string $path): ?string
    {
        try {
            $ruta = Storage::disk($disco)->path($path);
        } catch (\Throwable) {
            return null;
        }

        return is_file($ruta) ? $ruta : null;
    }

    private static function completo(string $disco, string $path, array $cabeceras): StreamedResponse
    {
        $cabeceras['Content-Length'] = (string) Storage::disk($disco)->size($path);

        return Storage::disk($disco)->response($path, null, $cabeceras);
    }

    /**
     * @return array{0: int, 1: int}|null|false null = sin rango (todo el archivo); false = rango inválido
     */
    public static function rango(string $cabecera, int $tamano): array|null|false
    {
        if ($cabecera === '') {
            return null;
        }
        if (preg_match('/^bytes=(\d*)-(\d*)$/', trim($cabecera), $m) !== 1) {
            return false;
        }
        [, $ini, $fin] = $m;
        if ($ini === '' && $fin === '') {
            return false;
        }
        if ($ini === '') { // últimos N bytes
            $n = (int) $fin;
            if ($n <= 0) {
                return false;
            }
            $desde = max(0, $tamano - $n);
            $hasta = $tamano - 1;
        } else {
            $desde = (int) $ini;
            $hasta = $fin === '' ? $tamano - 1 : min((int) $fin, $tamano - 1);
        }
        if ($desde > $hasta || $desde >= $tamano) {
            return false;
        }

        return [$desde, $hasta];
    }
}
