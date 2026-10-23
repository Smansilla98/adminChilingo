<?php

namespace App\Http\Controllers;

use App\Support\MediosDiseno;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sirve /storage/disenos/... desde el disco persistente. Son las mismas URL públicas de
 * siempre (nombres aleatorios), así el editor, la app y los diseños guardados no cambian.
 */
class MedioDisenoController extends Controller
{
    private const TIPOS = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif'];

    public function __invoke(string $path): BinaryFileResponse
    {
        $path = 'disenos/'.$path;
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        abort_if(str_contains($path, '..') || ! isset(self::TIPOS[$extension]), 404);
        $archivo = MediosDiseno::rutaLocal($path);
        abort_unless($archivo !== null, 404);

        return response()->file($archivo, [
            'Content-Type' => self::TIPOS[$extension],
            // Las miniaturas se pisan con el mismo nombre: esas no se cachean para siempre.
            'Cache-Control' => str_starts_with($path, 'disenos/previews/') ? 'public, max-age=60' : 'public, max-age=31536000, immutable',
        ]);
    }
}
