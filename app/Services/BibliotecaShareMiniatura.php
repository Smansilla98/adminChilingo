<?php

namespace App\Services;

use App\Models\BibliotecaItem;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BibliotecaShareMiniatura
{
    public const ANCHO = 1200;

    public const ALTO = 630;

    public function responder(BibliotecaItem $item): StreamedResponse
    {
        try {
            $rel = $this->asegurarJpeg($item);
        } catch (\Throwable $e) {
            report($e);
            $rel = $this->asegurarJpegPlano($item);
        }

        return Storage::disk('comprobantes')->response($rel, 'miniatura.jpg', [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
            'Content-Disposition' => 'inline; filename="miniatura.jpg"',
        ]);
    }

    public function asegurarJpeg(BibliotecaItem $item): string
    {
        $rel = 'biblioteca/og/'.$item->id.'-'.$item->updated_at?->timestamp.'.jpg';
        $disk = Storage::disk('comprobantes');
        if ($disk->exists($rel)) {
            return $rel;
        }

        foreach ($disk->files('biblioteca/og') as $viejo) {
            if (str_starts_with(basename($viejo), $item->id.'-') && $viejo !== $rel) {
                $disk->delete($viejo);
            }
        }

        $jpeg = $this->generarJpeg($item);
        $disk->put($rel, $jpeg);

        return $rel;
    }

    private function asegurarJpegPlano(BibliotecaItem $item): string
    {
        $rel = 'biblioteca/og/'.$item->id.'-fallback.jpg';
        $disk = Storage::disk('comprobantes');
        if (! $disk->exists($rel)) {
            $disk->put($rel, $this->jpegRespaldoPlano($item));
        }

        return $rel;
    }

    private function generarJpeg(BibliotecaItem $item): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return $this->jpegRespaldoPlano($item);
        }

        $base = $this->lienzoBase();
        $foto = $this->cargarFoto($item);
        if ($foto) {
            $this->cubrir($base, $foto);
            imagedestroy($foto);
        }

        $this->franjaInferior($base);
        $this->dibujarTextos($base, $item);

        $logo = $this->cargarLogo();
        if ($logo) {
            $lw = imagesx($logo);
            $lh = imagesy($logo);
            $dest = 64;
            imagecopyresampled($base, $logo, 48, 40, 0, 0, $dest, $dest, $lw, $lh);
            imagedestroy($logo);
        }

        ob_start();
        imagejpeg($base, null, 84);
        $bin = (string) ob_get_clean();
        imagedestroy($base);

        return $bin !== '' ? $bin : $this->jpegRespaldoPlano($item);
    }

    /** @return \GdImage|resource|null */
    private function cargarFoto(BibliotecaItem $item)
    {
        if ($item->path && $item->esImagen() && Storage::disk('comprobantes')->exists($item->path)) {
            $raw = Storage::disk('comprobantes')->get($item->path);
            $info = @getimagesizefromstring($raw);
            if ($info && (($info[0] ?? 0) * ($info[1] ?? 0) > 20000000)) {
                $raw = null;
            }
            $img = $raw ? @imagecreatefromstring($raw) : false;
            if ($img) {
                return $img;
            }
        }

        if ($item->path && $item->esVideo() && Storage::disk('comprobantes')->exists($item->path)) {
            $frame = $this->fotogramaVideo(Storage::disk('comprobantes')->path($item->path));
            if ($frame) {
                return $frame;
            }
        }

        return null;
    }

    /** @return \GdImage|resource|null */
    private function fotogramaVideo(string $absPath)
    {
        if (! function_exists('shell_exec') || ! function_exists('exec') || ! is_file($absPath)) {
            return null;
        }
        $ffmpeg = trim((string) shell_exec('command -v ffmpeg 2>/dev/null'));
        if ($ffmpeg === '') {
            return null;
        }

        $tmp = sys_get_temp_dir().'/biblio-og-'.uniqid('', true).'.jpg';
        $cmd = sprintf(
            '%s -y -ss 1 -i %s -frames:v 1 -vf %s -q:v 5 %s 2>/dev/null',
            escapeshellcmd($ffmpeg),
            escapeshellarg($absPath),
            escapeshellarg('scale=1200:630:force_original_aspect_ratio=increase,crop=1200:630'),
            escapeshellarg($tmp)
        );
        exec($cmd, $out, $code);
        if ($code !== 0 || ! is_file($tmp) || filesize($tmp) < 32) {
            @unlink($tmp);

            return null;
        }

        $img = @imagecreatefromjpeg($tmp);
        @unlink($tmp);

        return $img ?: null;
    }

    /** @return \GdImage|resource */
    private function lienzoBase()
    {
        $im = imagecreatetruecolor(self::ANCHO, self::ALTO);
        $fondo = imagecolorallocate($im, 10, 14, 26);
        imagefilledrectangle($im, 0, 0, self::ANCHO, self::ALTO, $fondo);
        $acento = imagecolorallocate($im, 62, 123, 250);
        imagefilledrectangle($im, 0, 0, 12, self::ALTO, $acento);

        return $im;
    }

    /**
     * @param  \GdImage|resource  $destino
     * @param  \GdImage|resource  $foto
     */
    private function cubrir($destino, $foto): void
    {
        $dw = self::ANCHO;
        $dh = self::ALTO;
        $sw = imagesx($foto);
        $sh = imagesy($foto);
        if ($sw < 1 || $sh < 1) {
            return;
        }
        $scale = max($dw / $sw, $dh / $sh);
        $nw = (int) round($sw * $scale);
        $nh = (int) round($sh * $scale);
        $x = (int) round(($dw - $nw) / 2);
        $y = (int) round(($dh - $nh) / 2);
        imagecopyresampled($destino, $foto, $x, $y, 0, 0, $nw, $nh, $sw, $sh);
    }

    /** @param  \GdImage|resource  $im */
    private function franjaInferior($im): void
    {
        $h = 210;
        $y0 = self::ALTO - $h;
        for ($i = 0; $i < $h; $i++) {
            $alpha = (int) min(110, (int) round(20 + ($i / $h) * 95));
            $c = imagecolorallocatealpha($im, 8, 12, 22, 127 - $alpha);
            imageline($im, 0, $y0 + $i, self::ANCHO, $y0 + $i, $c);
        }
    }

    /** @param  \GdImage|resource  $im */
    private function dibujarTextos($im, BibliotecaItem $item): void
    {
        $blanco = imagecolorallocate($im, 233, 237, 245);
        $muted = imagecolorallocate($im, 168, 179, 201);
        $acento = imagecolorallocate($im, 62, 123, 250);
        $font = $this->fuente(true);
        $fontReg = $this->fuente(false);
        $tipo = strtoupper(BibliotecaItem::TIPOS[$item->tipo] ?? 'Recurso');
        $titulo = $this->sanear($item->titulo ?: 'Material de la biblioteca');
        $meta = $this->sanear($item->etiquetaToqueInstrumento() ?: 'Biblioteca · La Chilinga');

        if ($font) {
            imagettftext($im, 18, 0, 48, 128, $acento, $font, $tipo);
            $lineas = $this->envolver($titulo, $font, 42, 1100);
            $y = 430;
            foreach (array_slice($lineas, 0, 2) as $linea) {
                imagettftext($im, 42, 0, 48, $y, $blanco, $font, $linea);
                $y += 56;
            }
            imagettftext($im, 20, 0, 48, min($y + 8, 600), $muted, $fontReg ?: $font, $meta);
        } else {
            imagestring($im, 5, 48, 90, $tipo, $acento);
            imagestring($im, 5, 48, 480, substr($titulo, 0, 60), $blanco);
            imagestring($im, 3, 48, 520, substr($meta, 0, 70), $muted);
        }
    }

    /** @return \GdImage|resource|null */
    private function cargarLogo()
    {
        $path = public_path('images/brand/logo.png');
        if (! is_file($path)) {
            return null;
        }
        $img = @imagecreatefrompng($path);

        return $img ?: null;
    }

    private function fuente(bool $bold): ?string
    {
        $cands = $bold
            ? [
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
                '/usr/share/fonts/truetype/freefont/FreeSansBold.ttf',
            ]
            : [
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
                '/usr/share/fonts/truetype/freefont/FreeSans.ttf',
            ];
        foreach ($cands as $f) {
            if (is_file($f)) {
                return $f;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function envolver(string $texto, string $font, int $size, int $maxPx): array
    {
        $palabras = preg_split('/\s+/', $texto) ?: [];
        $lineas = [];
        $actual = '';
        foreach ($palabras as $p) {
            $prueba = $actual === '' ? $p : $actual.' '.$p;
            $box = imagettfbbox($size, 0, $font, $prueba);
            $w = abs(($box[2] ?? 0) - ($box[0] ?? 0));
            if ($w > $maxPx && $actual !== '') {
                $lineas[] = $actual;
                $actual = $p;
            } else {
                $actual = $prueba;
            }
        }
        if ($actual !== '') {
            $lineas[] = $actual;
        }

        return $lineas !== [] ? $lineas : [$texto];
    }

    private function sanear(string $t): string
    {
        $t = trim(preg_replace('/\s+/', ' ', $t) ?? $t);

        return mb_substr($t, 0, 140);
    }

    private function jpegRespaldoPlano(BibliotecaItem $item): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return base64_decode(
                '/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBxAQEA8PEA8QDw8PEA8PDw8PDw8PDw8PFREWFhURFRUYHSggGBolGxUVITEhJSkrLi4uFx8zODMtNygtLisBCgoKDg0OGxAQGy0lHyUtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLf/AABEIAAEAAQMBIgACEQEDEQH/xAAbAAEAAQUBAAAAAAAAAAAAAAAAAwECBAUGB//EABQBAQAAAAAAAAAAAAAAAAAAAAD/xAAUAQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAADhQH//xAAaEAADAQEBAQAAAAAAAAAAAAABAgMABBEh/9oACAEBAAE/AN9lYp2gqf/EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQIBAT8Af//EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQMBAT8Af//Z'
            ) ?: '';
        }
        $im = $this->lienzoBase();
        $this->dibujarTextos($im, $item);
        ob_start();
        imagejpeg($im, null, 84);
        $bin = (string) ob_get_clean();
        imagedestroy($im);

        return $bin;
    }
}
