<?php

namespace App\Domain\Archivo;

use App\Models\ArchivoFoto;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Conservación y derivados del archivo fotográfico.
 *
 * El original se guarda tal cual (con su EXIF) y nunca se sirve en público. La web
 * usa derivados WebP sin metadatos en 400/800/1200/2048 px de ancho, un placeholder
 * de 24 px en base64 y el color medio para pintar antes de que cargue la imagen.
 */
class ImagenesArchivo
{
    public const DISCO = 'comprobantes';

    public const MAX_KB = 40960;

    public const EXTENSIONES = 'jpg,jpeg,png,webp';

    /** Campos del EXIF que se copian a la base (sin GPS: queda solo en el original). */
    private const EXIF_CAMPOS = [
        'DateTimeOriginal', 'DateTime', 'Make', 'Model', 'Orientation', 'ExposureTime',
        'FNumber', 'ISOSpeedRatings', 'FocalLength', 'LensModel', 'Software', 'Artist', 'Copyright',
    ];

    /**
     * Guarda el original y devuelve los datos técnicos para la fila.
     *
     * @return array{path: string, nombre_original: string, mime: string, bytes: int, hash: string, ancho: ?int, alto: ?int, exif: ?array<string, mixed>}
     */
    public function guardarOriginal(UploadedFile $archivo): array
    {
        $info = @getimagesize($archivo->getRealPath());
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw ValidationException::withMessages(['archivo' => 'El archivo no es una imagen JPG, PNG o WebP válida.']);
        }

        $ext = image_type_to_extension($info[2], false) ?: 'jpg';
        $ext = $ext === 'jpeg' ? 'jpg' : $ext;
        $dir = 'archivo/originales/'.now()->format('Y/m');
        $nombre = Str::uuid().'.'.$ext;
        $guardado = $archivo->storeAs($dir, $nombre, self::DISCO);
        if (! $guardado) {
            throw ValidationException::withMessages(['archivo' => 'No se pudo guardar la imagen en el servidor. Reintentá.']);
        }

        $exif = $info[2] === IMAGETYPE_JPEG ? $this->leerExif($archivo->getRealPath()) : null;
        [$ancho, $alto] = [$info[0], $info[1]];
        if (in_array((int) ($exif['Orientation'] ?? 1), [5, 6, 7, 8], true)) {
            [$ancho, $alto] = [$alto, $ancho];
        }

        return [
            'path' => $dir.'/'.$nombre,
            'nombre_original' => mb_substr($archivo->getClientOriginalName(), 0, 255),
            'mime' => $info['mime'],
            'bytes' => (int) $archivo->getSize(),
            'hash' => hash_file('sha256', $archivo->getRealPath()),
            'ancho' => $ancho,
            'alto' => $alto,
            'exif' => $exif,
        ];
    }

    public static function hashDe(UploadedFile $archivo): string
    {
        return hash_file('sha256', $archivo->getRealPath());
    }

    /**
     * Genera (o regenera) los derivados. Si GD no puede decodificar la imagen, la foto
     * queda sin derivados y la ruta pública responde 404 en lugar de exponer el original.
     */
    public function generarDerivados(ArchivoFoto $foto): ArchivoFoto
    {
        $disco = Storage::disk(self::DISCO);
        if (! $foto->path || ! $disco->exists($foto->path)) {
            return $foto;
        }

        $memoria = ini_get('memory_limit');
        @ini_set('memory_limit', '768M');
        try {
            $imagen = @imagecreatefromstring((string) $disco->get($foto->path));
            if (! $imagen instanceof GdImage) {
                return $foto;
            }
            $imagen = $this->orientar($imagen, (int) ($foto->exif['Orientation'] ?? 1));
            $ancho = imagesx($imagen);
            $alto = imagesy($imagen);

            $this->borrarDerivados($foto);
            $derivados = [];
            $hechos = [];
            foreach (ArchivoFoto::ANCHOS as $clave) {
                $w = min($clave, $ancho);
                if (isset($hechos[$w])) {
                    continue;
                }
                $h = (int) max(1, round($alto * $w / $ancho));
                $copia = $this->redimensionar($imagen, $w, $h);
                [$bytes, $ext] = $this->codificar($copia, $clave >= 2048 ? 82 : 80);
                imagedestroy($copia);
                $ruta = 'archivo/derivados/'.$foto->id.'/'.$clave.'.'.$ext;
                $disco->put($ruta, $bytes);
                $derivados[(string) $clave] = ['path' => $ruta, 'w' => $w, 'h' => $h];
                $hechos[$w] = true;
            }

            $mini = $this->redimensionar($imagen, 24, (int) max(1, round($alto * 24 / $ancho)));
            [$bytesMini, $extMini] = $this->codificar($mini, 40);
            $punto = $this->redimensionar($mini, 1, 1);
            $rgb = imagecolorat($punto, 0, 0);
            imagedestroy($punto);
            imagedestroy($mini);
            imagedestroy($imagen);

            $foto->forceFill([
                'ancho' => $ancho,
                'alto' => $alto,
                'derivados' => $derivados,
                'placeholder' => 'data:image/'.($extMini === 'jpg' ? 'jpeg' : $extMini).';base64,'.base64_encode($bytesMini),
                'color' => sprintf('#%02x%02x%02x', ($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF),
            ])->saveQuietly();
        } catch (\Throwable $e) {
            report($e);
        } finally {
            @ini_set('memory_limit', (string) $memoria);
        }

        return $foto;
    }

    /** Respuesta HTTP del derivado. Las URL llevan versión, así que se cachean un año. */
    public function responder(ArchivoFoto $foto, int $ancho): Response
    {
        if (empty($foto->derivados)) {
            $this->generarDerivados($foto);
        }
        $clave = (string) $foto->anchoDisponible($ancho);
        $ruta = $foto->derivados[$clave]['path'] ?? null;
        $disco = Storage::disk(self::DISCO);
        abort_unless($ruta && $disco->exists($ruta), 404);

        return $disco->response($ruta, null, [
            'Content-Type' => str_ends_with($ruta, '.webp') ? 'image/webp' : 'image/jpeg',
            'Cache-Control' => $foto->esPublica() ? 'public, max-age=31536000, immutable' : 'private, max-age=600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Descarga del original (solo para quien gestiona el archivo o quien lo aportó). */
    public function descargarOriginal(ArchivoFoto $foto): Response
    {
        $disco = Storage::disk(self::DISCO);
        abort_unless($foto->path && $disco->exists($foto->path), 404);

        return $disco->download($foto->path, $foto->nombre_original ?: basename($foto->path));
    }

    public function borrarDerivados(ArchivoFoto $foto): void
    {
        if ($foto->id) {
            Storage::disk(self::DISCO)->deleteDirectory('archivo/derivados/'.$foto->id);
        }
    }

    public function eliminar(ArchivoFoto $foto): void
    {
        $this->borrarDerivados($foto);
        if ($foto->path) {
            Storage::disk(self::DISCO)->delete($foto->path);
        }
    }

    /** @return array<string, mixed>|null */
    private function leerExif(string $ruta): ?array
    {
        if (! function_exists('exif_read_data')) {
            return null;
        }
        $datos = @exif_read_data($ruta, null, false);
        if (! is_array($datos)) {
            return null;
        }
        $salida = [];
        foreach (self::EXIF_CAMPOS as $campo) {
            if (isset($datos[$campo]) && is_scalar($datos[$campo])) {
                $salida[$campo] = mb_convert_encoding(mb_substr((string) $datos[$campo], 0, 120), 'UTF-8', 'UTF-8');
            }
        }

        return $salida === [] ? null : $salida;
    }

    private function orientar(GdImage $img, int $orientacion): GdImage
    {
        $rotar = match ($orientacion) {
            3, 4 => 180,
            5, 6 => -90,
            7, 8 => 90,
            default => 0,
        };
        if ($rotar !== 0) {
            $rotada = imagerotate($img, $rotar, 0);
            if ($rotada instanceof GdImage) {
                imagedestroy($img);
                $img = $rotada;
            }
        }
        if (in_array($orientacion, [2, 4, 5, 7], true)) {
            imageflip($img, IMG_FLIP_HORIZONTAL); // con el giro previo, 4 queda como espejo vertical
        }

        return $img;
    }

    private function redimensionar(GdImage $origen, int $w, int $h): GdImage
    {
        $destino = imagecreatetruecolor($w, $h);
        imagealphablending($destino, false);
        imagesavealpha($destino, true);
        imagefill($destino, 0, 0, imagecolorallocatealpha($destino, 0, 0, 0, 127));
        imagecopyresampled($destino, $origen, 0, 0, 0, 0, $w, $h, imagesx($origen), imagesy($origen));

        return $destino;
    }

    /** @return array{0: string, 1: string} bytes y extensión */
    private function codificar(GdImage $img, int $calidad): array
    {
        ob_start();
        if (function_exists('imagewebp') && (imagetypes() & IMG_WEBP)) {
            imagewebp($img, null, $calidad);
            $ext = 'webp';
        } else {
            // Sin WebP: JPEG sobre fondo neutro (JPEG no tiene transparencia).
            $plano = imagecreatetruecolor(imagesx($img), imagesy($img));
            imagefill($plano, 0, 0, imagecolorallocate($plano, 17, 17, 17));
            imagecopy($plano, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
            imageinterlace($plano, true);
            imagejpeg($plano, null, $calidad + 2);
            imagedestroy($plano);
            $ext = 'jpg';
        }

        return [(string) ob_get_clean(), $ext];
    }
}
