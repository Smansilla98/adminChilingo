<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProgramaRitmo;
use App\Services\PartituraHistorialService;
use App\Services\ProgramaRitmoMediosService;
use App\Support\PartituraMuestras;
use App\Support\PartituraScore;
use App\Support\ProgramaRitmoMedios;
use App\Support\ProgramaRitmoSlug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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
        $q = ProgramaRitmo::query();
        if (! ($request->boolean('todas') && $request->user()->acceso()->puede('partituras.admin'))) {
            $q->where('publicado', true);
        }
        $toques = ProgramaRitmo::traerOrdenados($q);

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

    /** Alta de un toque con partitura vacía, lista para escribir en la app. */
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->acceso()->puede('partituras.admin'), 403);
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'anio' => 'required|integer|min:1|max:7',
            'autor' => 'nullable|string|max:500',
        ]);

        $anio = (int) $data['anio'];
        $orden = (int) ProgramaRitmo::query()->where('año', $anio)->max('orden') + 1;
        $attrs = [
            'año' => $anio,
            'orden' => max(1, $orden),
            'nombre' => $data['nombre'],
            'autor' => $data['autor'] ?? null,
            'slug' => ProgramaRitmoSlug::generar($anio, $data['nombre']),
            'publicado' => false,
            'medios' => array_merge(ProgramaRitmoMedios::estructuraVacia(), [
                'partitura_score' => PartituraScore::vacia($data['nombre'], (string) ($data['autor'] ?? '')),
            ]),
        ];
        $toque = ProgramaRitmo::query()->create($attrs);

        return response()->json([
            'ok' => true,
            'slug' => $toque->slug,
            'publicado' => false,
        ], 201);
    }

    public function destroy(Request $request, string $slug): JsonResponse
    {
        abort_unless($request->user()->acceso()->puede('partituras.admin'), 403);
        $toque = ProgramaRitmo::query()->where('slug', $slug)->firstOrFail();
        $path = $toque->mediosNormalizados()['partitura']['path'] ?? null;
        if (is_string($path) && $path !== '') {
            Storage::disk('comprobantes')->delete($path);
        }
        $toque->delete();

        return response()->json(['ok' => true]);
    }

    /** One-shots del reproductor. La app no pide /sounds del sitio. */
    public function muestras(Request $request): JsonResponse
    {
        abort_unless($request->user()->acceso()->puede('partituras.view'), 403);

        return response()->json(['data' => PartituraMuestras::listar()]);
    }

    public function muestra(Request $request, string $archivo): BinaryFileResponse
    {
        abort_unless($request->user()->acceso()->puede('partituras.view'), 403);
        $ruta = PartituraMuestras::ruta($archivo);
        abort_unless($ruta !== null, 404, 'No está ese sonido.');

        return response()->file($ruta, [
            'Content-Type' => 'audio/wav',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /** Guarda la notación v4. La app escribe la grilla; el servidor normaliza. */
    public function guardarScore(Request $request, string $slug): JsonResponse
    {
        abort_unless($request->user()->acceso()->puede('partituras.admin'), 403);
        $toque = ProgramaRitmo::query()->where('slug', $slug)->firstOrFail();
        $data = $request->validate([
            'score' => 'required|array',
        ]);
        $score = PartituraScore::normalizar($data['score']);
        if ($score === null) {
            return response()->json(['message' => 'La partitura está vacía o es inválida.'], 422);
        }
        $nombre = trim((string) ($request->user()->name ?: $request->user()->username ?: 'App'));
        if (mb_strlen($nombre) < 2) {
            $nombre = 'App';
        }
        $medios = app(ProgramaRitmoMediosService::class)->guardarPartituraScore(
            $toque,
            $score,
            $nombre,
            $request->ip()
        );
        $toque->update(['medios' => $medios]);
        $version = app(PartituraHistorialService::class)->registrarVersion($toque, $medios['partitura_score'], $nombre, $request->input('nota'));

        return response()->json([
            'ok' => true,
            'score' => $medios['partitura_score'],
            'lectura' => PartituraScore::lectura($medios['partitura_score']),
            'version' => $version?->numero,
        ]);
    }

    /** Versiones publicadas de la partitura (más nueva primero). */
    public function versiones(string $slug, PartituraHistorialService $historial): JsonResponse
    {
        $toque = ProgramaRitmo::query()->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => $historial->listar($toque)]);
    }

    public function version(string $slug, int $numero, PartituraHistorialService $historial): JsonResponse
    {
        $toque = ProgramaRitmo::query()->where('slug', $slug)->firstOrFail();
        $v = $historial->version($toque, $numero);
        abort_unless($v, 404);

        return response()->json(['data' => ['numero' => $v->numero, 'autor' => $v->autor, 'nota' => $v->nota, 'fecha' => $v->created_at?->toIso8601String(), 'score' => $v->score]]);
    }

    /** PDF o imagen de referencia del toque. */
    public function subirArchivo(Request $request, string $slug): JsonResponse
    {
        abort_unless($request->user()->acceso()->puede('partituras.admin'), 403);
        $toque = ProgramaRitmo::query()->where('slug', $slug)->firstOrFail();
        $request->validate([
            'partitura_archivo' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:20480',
        ]);
        $medios = app(ProgramaRitmoMediosService::class)->actualizarSoloPartitura($request, $toque);
        $toque->update(['medios' => $medios]);

        return response()->json([
            'ok' => true,
            'tiene_pdf' => ! empty($medios['partitura']['path']),
        ]);
    }

    /** Datos del toque, incluida la notación para escribirla y escucharla. */
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
        $toque = $this->visible($request, $slug);
        $medios = $toque->mediosNormalizados();
        $score = is_array($medios['partitura_score'] ?? null) ? $medios['partitura_score'] : null;
        $normalizado = PartituraScore::normalizar($score);

        return response()->json([
            'slug' => $toque->slug,
            'nombre' => $toque->nombre,
            'anio' => (int) $toque->año,
            'autor' => $toque->autor,
            'resumen' => $toque->resumen,
            'publicado' => (bool) $toque->publicado,
            'tiene_pdf' => ! empty($medios['partitura']['path']),
            'lectura' => PartituraScore::lectura($normalizado ?? $score),
            'score' => $normalizado,
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
        $toque = $this->visible($request, $slug);
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

    /** Quien administra ve también los toques ocultos. El resto, solo los publicados. */
    private function visible(Request $request, string $slug): ProgramaRitmo
    {
        $q = ProgramaRitmo::query()->where('slug', $slug);
        $admin = $request->user()->acceso()->puede('partituras.admin');
        if (! $admin && Schema::hasColumn('programa_ritmos', 'publicado')) {
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
