<?php

namespace App\Http\Controllers;

use App\Models\ProgramaRitmo;
use App\Services\ProgramaRitmoMediosService;
use App\Support\PartituraScore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Editor de partituras v4 (tipo MuseScore) para los toques del programa.
 */
class PartituraController extends Controller
{
    /** Editor completo (solo admin). */
    public function editor(ProgramaRitmo $programaRitmo)
    {
        $this->soloAdmin();

        $medios = $programaRitmo->mediosNormalizados();
        $score = $medios['partitura_score'] ?? null;

        if (! is_array($score)) {
            $score = PartituraScore::vacia($programaRitmo->nombre, (string) $programaRitmo->autor);
        }

        return view('programa.partitura-editor', [
            'programaRitmo' => $programaRitmo,
            'score' => $score,
        ]);
    }

    /** Guardado del editor (JSON). */
    public function guardar(Request $request, ProgramaRitmo $programaRitmo): JsonResponse
    {
        $this->soloAdmin();

        $data = $request->validate([
            'score' => 'nullable|array',
            'quitar' => 'nullable|boolean',
        ]);

        if (! Schema::hasColumn('programa_ritmos', 'medios')) {
            return response()->json(['ok' => false, 'error' => 'La tabla no tiene la columna medios.'], 422);
        }

        $quitar = (bool) ($data['quitar'] ?? false);
        $score = $quitar ? null : PartituraScore::normalizar($data['score'] ?? null);

        if (! $quitar && $score === null) {
            return response()->json(['ok' => false, 'error' => 'La partitura está vacía o es inválida.'], 422);
        }

        $medios = app(ProgramaRitmoMediosService::class)->guardarPartituraScore($programaRitmo, $score);
        $programaRitmo->update(['medios' => $medios]);

        return response()->json([
            'ok' => true,
            'score' => $medios['partitura_score'],
            'resumen' => PartituraScore::resumen($medios['partitura_score']),
        ]);
    }

    /** Parte separada por instrumento, lista para imprimir. */
    public function parte(ProgramaRitmo $programaRitmo, string $instrumento)
    {
        if (Schema::hasColumn('programa_ritmos', 'publicado')
            && ! $programaRitmo->publicado
            && ! auth()->user()?->isAdmin()) {
            abort(404);
        }

        if (! array_key_exists($instrumento, PartituraScore::INSTRUMENTOS)) {
            abort(404);
        }

        $score = $programaRitmo->mediosNormalizados()['partitura_score'] ?? null;
        if (! is_array($score)) {
            abort(404);
        }

        $tiene = collect($score['instruments'] ?? [])->contains(fn ($i) => ($i['id'] ?? null) === $instrumento);
        if (! $tiene) {
            abort(404);
        }

        return view('programa.partitura-parte', [
            'programaRitmo' => $programaRitmo,
            'score' => $score,
            'instrumento' => $instrumento,
            'instrumentoLabel' => PartituraScore::INSTRUMENTOS[$instrumento],
        ]);
    }

    private function soloAdmin(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }
}
