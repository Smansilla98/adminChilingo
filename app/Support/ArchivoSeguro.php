<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Archivos subidos por usuarios (biblioteca, cortes y recursos del programa).
 *
 * El tipo con que se sirve sale SIEMPRE de una lista fija por extensión, nunca de lo que
 * detectó el servidor ni de lo que mandó el navegador: así un «.pdf» que en realidad es
 * HTML o SVG no se ejecuta en el dominio del panel. Lo que no está en la lista se descarga.
 */
final class ArchivoSeguro
{
    /** Extensión → tipo con que se muestra en el navegador. */
    public const TIPOS = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif',
        'mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime',
        'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg', 'm4a' => 'audio/mp4',
        'pdf' => 'application/pdf',
    ];

    public static function tipoPorExtension(string $nombre): ?string
    {
        return self::TIPOS[strtolower(pathinfo($nombre, PATHINFO_EXTENSION))] ?? null;
    }

    /** ¿El contenido real del archivo es de la misma familia que su extensión (imagen, video, audio, PDF)? */
    public static function contenidoCoincide(UploadedFile $archivo): bool
    {
        $esperado = self::tipoPorExtension($archivo->getClientOriginalName());
        if ($esperado === null) {
            return false;
        }
        $detectado = strtolower((string) $archivo->getMimeType());
        if ($detectado === '' || $detectado === 'application/octet-stream') {
            // Algunos audios y videos no se reconocen; se aceptan, pero se servirán con el tipo de la lista.
            return ! str_starts_with($esperado, 'image/') && $esperado !== 'application/pdf';
        }
        if (str_contains($detectado, 'svg') || str_contains($detectado, 'html') || str_contains($detectado, 'xml') || str_contains($detectado, 'javascript')) {
            return false;
        }
        $familia = fn (string $mime) => $mime === 'application/pdf' ? 'pdf' : explode('/', $mime)[0];
        // Contenedores MP4/MOV a veces se detectan como audio o video indistintamente.
        $multimedia = ['audio', 'video'];
        if (in_array($familia($esperado), $multimedia, true) && in_array($familia($detectado), $multimedia, true)) {
            return true;
        }

        return $familia($esperado) === $familia($detectado);
    }

    /** Regla de validación para usar junto a `file|extensions:...`. */
    public static function regla(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value instanceof UploadedFile && ! self::contenidoCoincide($value)) {
                $fail('El contenido del archivo no coincide con su formato. Subí una imagen, video, audio o PDF real.');
            }
        };
    }

    /** Respuesta para mostrar o descargar un archivo guardado, con encabezados que impiden ejecutar contenido. */
    public static function responder(string $disco, string $path, string $nombre, bool $enLinea = true, array $extra = []): StreamedResponse
    {
        $tipo = self::tipoPorExtension($path) ?? self::tipoPorExtension($nombre);
        $nombreSeguro = str_replace(['"', '\\', "\r", "\n"], '', $nombre);
        $disposicion = ($enLinea && $tipo !== null ? 'inline' : 'attachment').'; filename="'.$nombreSeguro.'"';

        $headers = array_merge($extra, [
            'Content-Type' => $tipo ?? 'application/octet-stream',
            'Content-Disposition' => $disposicion,
            'X-Content-Type-Options' => 'nosniff',
        ]);
        if ($tipo !== 'application/pdf') {
            // Chrome no abre PDFs con sandbox; al resto (imagen, audio, video, descarga) no le afecta.
            $headers['Content-Security-Policy'] = "sandbox; default-src 'none'; img-src 'self'; media-src 'self'; style-src 'unsafe-inline'";
        }

        return Storage::disk($disco)->response($path, $nombreSeguro, $headers);
    }
}
