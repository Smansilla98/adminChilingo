<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProgramaRitmo;
use App\Support\PartituraScore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Programa y partituras. El visor interactivo (VexFlow + audio) es la vista web
 * existente: la app la abre a pantalla completa con `visor_url`.
 */
class PartituraController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->acceso()->puede('partituras.view'), 403);
        $toques = ProgramaRitmo::query()->where('publicado', true)->orderBy('año')->orderBy('orden')
            ->get(['id', 'slug', 'nombre', 'año', 'orden', 'autor', 'opcional', 'medios']);

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
            ];
        })->values()]);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        abort_unless($request->user()->acceso()->puede('partituras.view'), 403);
        $toque = ProgramaRitmo::query()->where('slug', $slug)->where('publicado', true)->firstOrFail();
        $medios = $toque->mediosNormalizados();
        $score = $medios['partitura_score'] ?? null;
        $instrumentos = collect($score['instruments'] ?? [])->pluck('id')->filter()->values();

        return response()->json([
            'slug' => $toque->slug,
            'nombre' => $toque->nombre,
            'anio' => (int) $toque->año,
            'autor' => $toque->autor,
            'resumen' => $toque->resumen,
            'visor_url' => route('programa.toque.show', $toque),
            'pdf_url' => ! empty($medios['partitura']['path']) ? route('programa.toque.archivo', $toque) : null,
            'partes' => $instrumentos->map(fn ($id) => [
                'instrumento' => $id,
                'nombre' => PartituraScore::INSTRUMENTOS[$id] ?? $id,
                'url' => route('programa.toque.parte', [$toque, $id]),
            ])->values(),
            'videos' => collect($medios['videos_base'] ?? [])->filter(fn ($v) => ! empty($v['url']))->map(fn ($v, $k) => ['clave' => $k, 'url' => $v['url']])->values(),
            'score' => $request->boolean('con_score') ? $score : null,
        ]);
    }
}
