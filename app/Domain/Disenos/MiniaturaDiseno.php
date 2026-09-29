<?php

namespace App\Domain\Disenos;

use App\Models\Diseno;
use Illuminate\Support\Facades\Storage;

/**
 * Vista previa PNG a partir del JSON del lienzo, para no depender del editor web.
 */
class MiniaturaDiseno
{
    public function guardar(Diseno $diseno, string $canvasJson): ?string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }
        $canvas = json_decode($canvasJson, true);
        if (! is_array($canvas)) {
            return null;
        }
        $ancho = max(50, (int) ($diseno->ancho ?: 1080));
        $alto = max(50, (int) ($diseno->alto ?: 1080));
        $destW = 480;
        $destH = max(1, (int) round($destW * $alto / $ancho));
        $img = imagecreatetruecolor($destW, $destH);
        if ($img === false) {
            return null;
        }
        $k = $destW / $ancho;
        $fondo = $this->color($img, (string) ($canvas['background'] ?? '#ffffff'));
        imagefilledrectangle($img, 0, 0, $destW, $destH, $fondo);
        foreach (is_array($canvas['objects'] ?? null) ? $canvas['objects'] : [] as $o) {
            if (! is_array($o)) {
                continue;
            }
            $sx = (float) ($o['scaleX'] ?? 1);
            $sy = (float) ($o['scaleY'] ?? 1);
            $w = (int) round((($o['type'] ?? '') === 'circle' ? ((float) ($o['radius'] ?? 0)) * 2 : (float) ($o['width'] ?? 100)) * $sx * $k);
            $h = (int) round((($o['type'] ?? '') === 'circle' ? ((float) ($o['radius'] ?? 0)) * 2 : (float) ($o['height'] ?? 40)) * $sy * $k);
            $x = (int) round(((float) ($o['left'] ?? 0)) * $k);
            $y = (int) round(((float) ($o['top'] ?? 0)) * $k);
            $color = $this->color($img, is_string($o['fill'] ?? null) ? $o['fill'] : '#f26422');
            if (($o['type'] ?? '') === 'rect' || ($o['type'] ?? '') === 'circle') {
                imagefilledrectangle($img, $x, $y, $x + max(1, $w), $y + max(1, $h), $color);
            } elseif (in_array($o['type'] ?? '', ['text', 'i-text', 'textbox'], true)) {
                imagestring($img, 3, $x, $y, substr((string) ($o['text'] ?? ''), 0, 40), $color);
            }
        }
        ob_start();
        imagepng($img);
        $binario = ob_get_clean();
        imagedestroy($img);
        if (! is_string($binario) || $binario === '') {
            return null;
        }
        $path = 'disenos/previews/'.$diseno->id.'.png';
        Storage::disk('public')->put($path, $binario);
        $diseno->preview_path = $path;
        $diseno->save();

        return $path;
    }

    private function color(\GdImage $img, string $hex): int
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            $hex = 'ffffff';
        }
        $c = imagecolorallocate($img, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));

        return $c === false ? 0 : $c;
    }
}
