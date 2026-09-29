<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProgramaRitmo;
use App\Support\PartituraScore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Programa y partituras para la app. El JSON y el PDF salen por la API:
 * la app no abre las páginas del panel web.
 */
class PartituraController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->acceso()->puede('partituras.view'), 403);
        $q = ProgramaRitmo::query()->orderBy('año')->orderBy('orden');
        if (! ($request->boolean('todas') && $request->user()->acceso()->puede('partituras.admin'))) {
            $q->where('publicado', true);
        }
        $toques = $q
            ->get(['id', 'slug', 'nombre', 'año', 'orden', 'autor', 'opcional', 'publicado', 'medios']);

        return response()->json(['data' => $toques->map(function (ProgramaRitmo $t) {
            $medios = $t->mediosNormalizados();

            return [
                'slug' => $t->slug,
                'nombre' => $t->nombre,
                'anio' => (int) $t->año,
                'orden' => (int) $t->orden,
                'autor' => $t->autor,
                'opcional' => (bool) $t->opcional,
                'tiene_partitura' => ! empty($medios['partitura_score']) || ! empty($medios['partitura']),
                'tiene_pdf' => ! empty($medios['partitura']['path']),
                'publicado' => (bool) $t->publicado,
            ];
        })->values()]);
    }

    /** Datos del toque. La edición de la notación sigue en el modelo de la API (`lectura`), no en el panel. */
    public function update(Request $request, string $slug): JsonResponse
    {
        abort_unless($request->user()->acceso()->puede('partituras.admin'), 403);
        $toque = ProgramaRitmo::query()->where('slug', $slug)->firstOrFail();
        $data = $request->validate([
            'nombre' => 'sometimes|required|string|max:255',
            'autor' => 'nullable|string|max:500',
            'resumen' => 'nullable|string|max:2000',
            'publicado' => 'sometimes|boolean',
        ]);
        $toque->fill($data)->save();

        return response()->json(['ok' => true, 'slug' => $toque->slug, 'publicado' => (bool) $toque->publicado]);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        abort_unless($request->user()->acceso()->puede('partituras.view'), 403);
        $toque = $this->publicado($slug);
        $medios = $toque->mediosNormalizados();
        $score = is_array($medios['partitura_score'] ?? null) ? $medios['partitura_score'] : null;

        return response()->json([
            'slug' => $toque->slug,
            'nombre' => $toque->nombre,
            'anio' => (int) $toque->año,
            'autor' => $toque->autor,
            'resumen' => $toque->resumen,
            'publicado' => (bool) $toque->publicado,
            'tiene_pdf' => ! empty($medios['partitura']['path']),
            'lectura' => PartituraScore::lectura($score),
            'videos' => collect($medios['videos_base'] ?? [])
                ->filter(fn ($v) => ! empty($v['url']) && ! $this->esDelPropioSitio((string) $v['url']))
                ->map(fn ($v, $k) => ['clave' => $k, 'url' => $v['url']])
                ->values(),
        ]);
    }

    /** PDF original del toque. Misma autorización que el listado; no usa la ruta web. */
    public function archivo(Request $request, string $slug): StreamedResponse
    {
        abort_unless($request->user()->acceso()->puede('partituras.view'), 403);
        $toque = $this->publicado($slug);
        $partitura = $toque->mediosNormalizados()['partitura'] ?? null;
        $path = is_array($partitura) ? ($partitura['path'] ?? null) : null;
        abort_unless(is_string($path) && $path !== '' && Storage::disk('comprobantes')->exists($path), 404, 'No hay PDF de esta partitura.');

        $nombre = is_string($partitura['nombre'] ?? null) && $partitura['nombre'] !== ''
            ? $partitura['nombre']
            : 'partitura-'.$toque->slug;

        return Storage::disk('comprobantes')->response($path, $nombre, [
            'Content-Disposition' => 'inline; filename="'.addslashes($nombre).'"',
        ]);
    }

    private function publicado(string $slug): ProgramaRitmo
    {
        $q = ProgramaRitmo::query()->where('slug', $slug);
        if (Schema::hasColumn('programa_ritmos', 'publicado')) {
            $q->where('publicado', true);
        }

        return $q->firstOrFail();
    }

    /** Un video alojado en el mismo host que la API es una página del panel, no un recurso externo. */
    private function esDelPropioSitio(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        $propio = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($host) && is_string($propio) && strcasecmp($host, $propio) === 0;
    }
}
