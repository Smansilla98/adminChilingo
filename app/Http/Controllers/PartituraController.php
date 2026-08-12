<?php

namespace App\Http\Controllers;

use App\Models\BibliotecaItem;
use App\Models\ProgramaRitmo;
use App\Services\ProgramaRitmoMediosService;
use App\Support\PartituraScore;
use App\Support\ProgramaRitmoMedios;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Editor de partituras v4 (tipo MuseScore) para los toques del programa.
 */
class PartituraController extends Controller
{
    /** Editor abierto: la firma se pide en un pop-up (sin crear usuarios). */
    public function editor(Request $request, ProgramaRitmo $programaRitmo)
    {
        $this->abortSiToqueNoPublico($programaRitmo);

        $medios = $programaRitmo->mediosNormalizados();
        $score = $medios['partitura_score'] ?? null;

        if (! is_array($score)) {
            $score = PartituraScore::vacia($programaRitmo->nombre, (string) $programaRitmo->autor);
        }

        $partitura = $medios['partitura'] ?? null;
        $refNombre = (string) ($partitura['nombre'] ?? '');
        $refUrl = ! empty($partitura['path'])
            ? route('programa.toque.archivo', [$programaRitmo, 'tipo' => 'partitura', 'inline' => 1])
            : null;
        $refTipo = preg_match('/\.pdf$/i', $refNombre) ? 'pdf' : 'imagen';

        $biblio = $this->referenciaDesdeBiblioteca($request, $programaRitmo);
        if ($biblio) {
            $refUrl = $biblio['url'];
            $refTipo = $biblio['tipo'];
            $refNombre = $biblio['nombre'];
        }

        return view('programa.partitura-editor', [
            'programaRitmo' => $programaRitmo,
            'score' => $score,
            'ultimaEdicion' => ProgramaRitmoMedios::ultimaEdicion($medios),
            'nombreSugerido' => auth()->user()?->name ?: auth()->user()?->username,
            'refUrl' => $refUrl,
            'refTipo' => $refTipo,
            'refNombre' => $refNombre,
        ]);
    }

    /** Sube un PDF/imagen de referencia para transcribirlo en el editor. */
    public function subirReferencia(Request $request, ProgramaRitmo $programaRitmo): JsonResponse
    {
        $this->abortSiToqueNoPublico($programaRitmo);

        $data = $request->validate([
            'editor_nombre' => 'required|string|min:2|max:80',
            'partitura_archivo' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:20480',
        ], [
            'editor_nombre.required' => 'Indicá tu nombre para dejar registro.',
            'partitura_archivo.required' => 'Elegí un PDF o una imagen de la partitura.',
        ]);

        if (! Schema::hasColumn('programa_ritmos', 'medios')) {
            return response()->json(['ok' => false, 'error' => 'La tabla no tiene la columna medios.'], 422);
        }

        $medios = app(ProgramaRitmoMediosService::class)->actualizarSoloPartitura($request, $programaRitmo);
        $medios = ProgramaRitmoMedios::registrarEdicion(
            $medios,
            (string) $data['editor_nombre'],
            $request->ip(),
            'partitura_ediciones'
        );
        $programaRitmo->update(['medios' => $medios]);

        $nombre = $medios['partitura']['nombre'] ?? $request->file('partitura_archivo')?->getClientOriginalName();

        return response()->json([
            'ok' => true,
            'url' => route('programa.toque.archivo', [$programaRitmo, 'tipo' => 'partitura', 'inline' => 1]),
            'es_pdf' => (bool) preg_match('/\.pdf$/i', (string) $nombre),
            'nombre' => $nombre,
        ]);
    }

    /** Guardado del editor (JSON). Requiere el nombre de quien edita. */
    public function guardar(Request $request, ProgramaRitmo $programaRitmo): JsonResponse
    {
        $this->abortSiToqueNoPublico($programaRitmo);

        $data = $request->validate([
            'score' => 'nullable|array',
            'quitar' => 'nullable|boolean',
            'editor_nombre' => 'required|string|min:2|max:80',
        ], [
            'editor_nombre.required' => 'Indicá tu nombre para dejar registro de la edición.',
            'editor_nombre.min' => 'El nombre debe tener al menos 2 caracteres.',
        ]);

        if (! Schema::hasColumn('programa_ritmos', 'medios')) {
            return response()->json(['ok' => false, 'error' => 'La tabla no tiene la columna medios.'], 422);
        }

        $quitar = (bool) ($data['quitar'] ?? false);
        if ($quitar && ! auth()->user()?->isAdmin()) {
            return response()->json(['ok' => false, 'error' => 'Solo administración puede quitar la partitura.'], 403);
        }

        $score = $quitar ? null : PartituraScore::normalizar($data['score'] ?? null);

        if (! $quitar && $score === null) {
            return response()->json(['ok' => false, 'error' => 'La partitura está vacía o es inválida.'], 422);
        }

        $nombre = trim((string) $data['editor_nombre']);
        $medios = app(ProgramaRitmoMediosService::class)->guardarPartituraScore(
            $programaRitmo,
            $score,
            $nombre,
            $request->ip()
        );
        $programaRitmo->update(['medios' => $medios]);

        return response()->json([
            'ok' => true,
            'score' => $medios['partitura_score'],
            'resumen' => PartituraScore::resumen($medios['partitura_score']),
            'editado_por' => $nombre,
        ]);
    }

    /** Parte separada por instrumento, lista para imprimir. */
    public function parte(ProgramaRitmo $programaRitmo, string $instrumento)
    {
        $this->abortSiToqueNoPublico($programaRitmo);

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

    /**
     * @return array{url: string, tipo: string, nombre: string}|null
     */
    private function referenciaDesdeBiblioteca(Request $request, ProgramaRitmo $programaRitmo): ?array
    {
        $id = $request->integer('biblio');
        if ($id < 1) {
            return null;
        }

        $item = BibliotecaItem::query()->publicados()->with('toque')->find($id);
        if (! $item || (int) $item->programa_ritmo_id !== (int) $programaRitmo->id) {
            return null;
        }

        $url = $item->archivoUrl();
        if (! $url || ! $item->esReferenciaDePartitura()) {
            return null;
        }

        $tipo = $item->esPdf() ? 'pdf' : ($item->esVideo() ? 'video' : 'imagen');

        return [
            'url' => $url,
            'tipo' => $tipo,
            'nombre' => $item->titulo ?: (string) $item->nombre_original,
        ];
    }

    private function abortSiToqueNoPublico(ProgramaRitmo $programaRitmo): void
    {
        if (Schema::hasColumn('programa_ritmos', 'publicado')
            && ! $programaRitmo->publicado
            && ! auth()->user()?->isAdmin()) {
            abort(404);
        }
    }
}
