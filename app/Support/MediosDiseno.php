<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Archivos del módulo Diseño (imágenes subidas, kit de marca, miniaturas).
 *
 * Se guardan en el disco persistente (`comprobantes`, PERSISTENT_STORAGE_PATH): el disco
 * `public` vive dentro del contenedor y Railway lo borra en cada deploy, y con él las
 * imágenes de todos los diseños. Las URL no cambian (/storage/disenos/...): si el archivo
 * no está en public/storage, la ruta `disenos.medio` lo sirve desde el disco persistente.
 */
final class MediosDiseno
{
    public static function disco(): Filesystem
    {
        return Storage::disk('comprobantes');
    }

    public static function guardarSubida(UploadedFile $archivo, string $carpeta): string
    {
        $path = trim($carpeta, '/').'/'.Str::uuid().'.'.($archivo->extension() ?: 'png');
        self::disco()->put($path, (string) file_get_contents($archivo->getRealPath()));

        return $path;
    }

    public static function guardar(string $path, string $binario): void
    {
        self::disco()->put($path, $binario);
    }

    public static function borrar(?string $path): void
    {
        if (! $path) {
            return;
        }
        self::disco()->delete($path);
        Storage::disk('public')->delete($path);
    }

    public static function url(string $path): string
    {
        return asset('storage/'.ltrim($path, '/'));
    }

    /** Ruta absoluta del archivo, o null. Primero el disco persistente, después el viejo. */
    public static function rutaLocal(string $path): ?string
    {
        foreach ([self::disco(), Storage::disk('public')] as $disco) {
            if ($disco->exists($path)) {
                return $disco->path($path);
            }
        }

        return null;
    }
}
