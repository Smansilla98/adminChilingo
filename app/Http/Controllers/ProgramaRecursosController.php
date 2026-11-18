<?php

namespace App\Http\Controllers;

use App\Models\ProgramaRitmo;
use App\Support\AccesoMaterial;
use App\Support\PartituraScore;
use App\Support\VideosMultipista;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * «Toques y recursos»: los toques publicados que tienen videos, partitura o grabación.
 * La lista es pública y solo muestra conteos; el reproductor (con URLs de los archivos) se
 * entrega únicamente a quien tiene acceso al material de ese año.
 */
class ProgramaRecursosController extends Controller
{
    public function index(Request $request)
    {
        $items = $this->publicados()
            ->map(fn (ProgramaRitmo $t) => self::resumen($t))
            ->filter(fn (array $r) => $r['videos'] > 0 || $r['partitura'] || $r['audio'])
            ->values();

        return view('programa.recursos', [
            'items' => $items,
            'anios' => $items->pluck('anio')->unique()->sort()->values(),
        ]);
    }

    /** Toques publicados y en el programa, en orden. Nunca borradores ni ocultos. */
    private function publicados()
    {
        $q = ProgramaRitmo::soloEnPrograma(ProgramaRitmo::query());
        if (Schema::hasColumn('programa_ritmos', 'publicado')) {
            $q->where('publicado', true);
        }

        return ProgramaRitmo::traerOrdenados($q);
    }

    /**
     * Datos públicos de un toque: nombre, año, orden y qué recursos tiene (solo conteos).
     *
     * @return array{slug: string, nombre: string, anio: int, orden: int, videos: int, partitura: bool, audio: bool}
     */
    public static function resumen(ProgramaRitmo $toque): array
    {
        $medios = $toque->mediosNormalizados();
        $score = PartituraScore::normalizar($medios['partitura_score'] ?? null);

        return [
            'slug' => (string) $toque->slug,
            'nombre' => (string) $toque->nombre,
            'anio' => (int) $toque->año,
            'orden' => (int) $toque->orden,
            'videos' => count(VideosMultipista::normalizar($medios['videos_multipista'] ?? [])),
            'partitura' => ($score !== null && PartituraScore::tieneGolpes($score)) || ! empty($medios['partitura']['path']),
            'audio' => ! empty($score['audio_ref']['path'] ?? null),
        ];
    }

    /** Página de un toque: reproductor de videos si el visitante puede ver ese material. */
    public function show(Request $request, ProgramaRitmo $programaRitmo)
    {
        abort_unless($this->esPublicado($programaRitmo), 404);
        $resumen = self::resumen($programaRitmo);
        $puedeVer = $request->user() !== null && AccesoMaterial::puedeVer($request->user(), $programaRitmo);
        $videos = collect(VideosMultipista::normalizar($programaRitmo->mediosNormalizados()['videos_multipista'] ?? []))
            ->sortBy('orden')->values();

        return view('programa.recurso-toque', [
            'toque' => $programaRitmo,
            'resumen' => $resumen,
            'puedeVer' => $puedeVer,
            'videosReproducir' => $puedeVer
                ? $videos->map(fn (array $v) => [
                    'id' => $v['id'],
                    'titulo' => $v['titulo'],
                    'instrumento' => $v['instrumento'],
                    'orden' => $v['orden'],
                    'offset_seg' => $v['offset_seg'],
                    'url' => route('programa.toque.video', [$programaRitmo, basename($v['archivo'])]),
                ])->all()
                : [],
            'totalVideos' => $videos->count(),
        ]);
    }

    private function esPublicado(ProgramaRitmo $toque): bool
    {
        if (Schema::hasColumn('programa_ritmos', 'publicado') && ! $toque->publicado) {
            return false;
        }

        return ! Schema::hasColumn('programa_ritmos', 'en_programa') || (bool) $toque->en_programa;
    }
}
