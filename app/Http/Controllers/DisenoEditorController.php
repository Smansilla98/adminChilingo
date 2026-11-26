<?php

namespace App\Http\Controllers;

use App\Domain\Disenos\CatalogoDiseno;
use App\Domain\Disenos\GestorDiseno;
use App\Models\Diseno;
use App\Models\DisenoKitAsset;
use App\Models\DisenoPagina;
use App\Support\MarcaChilinga;
use App\Support\MediosDiseno;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Módulo Diseño con el editor OpenDesign (resources/opendesign, MIT).
 * Implementa en Laravel la API que el editor esperaba de su backend original (Hono/D1),
 * sobre las tablas `disenos` y `diseno_paginas`.
 */
class DisenoEditorController extends Controller
{
    private const MAX_CANVAS = 5 * 1024 * 1024;

    /** SPA del editor: /disenos y /disenos/design/{id}. */
    public function app(Request $request): View
    {
        $this->authorize('viewAny', Diseno::class);

        return view('disenos.editor', [
            'config' => [
                'basePath' => '/disenos',
                'apiBase' => url('/disenos/api'),
                'panelUrl' => route('dashboard'),
                'logoUrl' => asset('images/brand/logo.png'),
                'csrfToken' => csrf_token(),
                'puedeGestionarKit' => $request->user()->can('manageKit', Diseno::class),
            ],
        ]);
    }

    // ── Diseños ────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Diseno::class);
        $user = $request->user();

        // Las plantillas de estudio (sin dueño) se ofrecen como plantillas, no como diseños propios.
        $query = Diseno::query()->whereNotNull('user_id')->latest('updated_at');
        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        return response()->json($query->limit(200)->get()->map->paraEditor()->values());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Diseno::class);
        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'canvas_json' => 'nullable|string|max:'.self::MAX_CANVAS,
            'width' => 'nullable|integer|min:50|max:10000',
            'height' => 'nullable|integer|min:50|max:10000',
        ]);
        $diseno = app(GestorDiseno::class)->crear(
            $data,
            $this->catalogo()->decodificarLienzo($data['canvas_json'] ?? '{}'),
            (int) ($data['width'] ?? 1080),
            (int) ($data['height'] ?? 1350),
            $request->user()->id,
        );

        return response()->json($diseno->paraEditor());
    }

    public function show(Diseno $diseno): JsonResponse
    {
        $this->authorize('view', $diseno);

        // Diseños del editor anterior: una página con su canvas.
        if (! $diseno->paginas()->exists()) {
            $diseno->paginas()->create([
                'titulo' => 'Página 1',
                'canvas_json' => $diseno->canvas_json ? json_encode($diseno->canvas_json) : '{}',
                'orden' => 0,
            ]);
        }

        return response()->json($diseno->paraEditor() + [
            'pages' => $diseno->paginas()->get()->map->paraEditor()->values(),
        ]);
    }

    public function update(Request $request, Diseno $diseno): JsonResponse
    {
        $this->authorize('update', $diseno);
        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'canvas_json' => 'nullable|string|max:'.self::MAX_CANVAS,
            'width' => 'nullable|integer|min:50|max:10000',
            'height' => 'nullable|integer|min:50|max:10000',
            'thumbnail_data' => 'nullable|string|max:2000000',
        ]);

        $canvas = array_key_exists('canvas_json', $data) && $data['canvas_json'] !== null
            ? $this->catalogo()->decodificarLienzo($data['canvas_json'])
            : null;
        app(GestorDiseno::class)->aplicarCambios($diseno, $data, $canvas);

        return response()->json($diseno->fresh()->paraEditor());
    }

    public function destroy(Diseno $diseno): JsonResponse
    {
        $this->authorize('delete', $diseno);
        MediosDiseno::borrar($diseno->preview_path);
        $diseno->delete();

        return response()->json(['ok' => true]);
    }

    // ── Páginas ────────────────────────────────────────────────────────

    public function storePage(Request $request, Diseno $diseno): JsonResponse
    {
        $this->authorize('update', $diseno);
        $data = $request->validate([
            'title' => 'nullable|string|max:120',
            'canvas_json' => 'nullable|string|max:'.self::MAX_CANVAS,
            'after_sort_order' => 'nullable|integer|min:0',
        ]);
        $this->catalogo()->decodificarLienzo($data['canvas_json'] ?? '{}');
        $pagina = app(GestorDiseno::class)->agregarPagina($diseno, $data);

        return response()->json($pagina->paraEditor());
    }

    public function duplicatePage(DisenoPagina $pagina): JsonResponse
    {
        $this->authorize('update', $pagina->diseno);

        $copia = app(GestorDiseno::class)->duplicarPagina($pagina);

        return response()->json($copia->paraEditor());
    }

    public function updatePage(Request $request, DisenoPagina $pagina): JsonResponse
    {
        $this->authorize('update', $pagina->diseno);
        $data = $request->validate([
            'title' => 'nullable|string|max:120',
            'canvas_json' => 'nullable|string|max:'.self::MAX_CANVAS,
        ]);
        $canvas = isset($data['canvas_json']) ? $this->catalogo()->decodificarLienzo($data['canvas_json']) : null;
        $pagina = app(GestorDiseno::class)->actualizarPagina($pagina, $data, $canvas);

        return response()->json($pagina->paraEditor());
    }

    public function destroyPage(DisenoPagina $pagina): JsonResponse
    {
        $this->authorize('update', $pagina->diseno);
        if (DisenoPagina::query()->where('diseno_id', $pagina->diseno_id)->count() <= 1) {
            return response()->json(['error' => 'No se puede eliminar la única página.'], 400);
        }
        $pagina->delete();

        return response()->json(['ok' => true]);
    }

    // ── Plantillas ─────────────────────────────────────────────────────

    public function templates(): JsonResponse
    {
        $this->authorize('viewAny', Diseno::class);

        return response()->json($this->catalogo()->plantillas()->values());
    }

    public function template(string $id): JsonResponse
    {
        $this->authorize('viewAny', Diseno::class);
        $plantilla = $this->catalogo()->plantillas()->firstWhere('id', $id);

        return $plantilla ? response()->json($plantilla) : response()->json(['error' => 'No existe la plantilla.'], 404);
    }

    // ── Imágenes y marca ───────────────────────────────────────────────

    private function catalogo(): CatalogoDiseno
    {
        return app(CatalogoDiseno::class);
    }

    /** Subida de imagen para usar en el lienzo. SVG no: se sirve desde el mismo dominio. */
    public function upload(Request $request): JsonResponse
    {
        $this->authorize('uploadAsset', Diseno::class);
        $request->validate(['file' => 'required|file|mimes:jpg,jpeg,png,webp,gif|max:10240'], [
            'file.mimes' => 'Formato no admitido. Usá PNG, JPG, WebP o GIF.',
            'file.max' => 'La imagen supera los 10 MB.',
        ]);
        $file = $request->file('file');
        $path = MediosDiseno::guardarSubida($file, 'disenos/medios/'.$request->user()->id);

        return response()->json(['url' => MediosDiseno::url($path)]);
    }

    /** Manual de marca vigente, para ver en el navegador. */
    public function manual(): BinaryFileResponse
    {
        $this->authorize('viewAny', Diseno::class);
        abort_unless(is_file(MarcaChilinga::manualPath()), 404);

        return response()->file(MarcaChilinga::manualPath(), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="La Chilinga - '.MarcaChilinga::VERSION.'.pdf"',
        ]);
    }

    public function marca(): JsonResponse
    {
        $this->authorize('uploadAsset', Diseno::class);

        return response()->json(app(CatalogoDiseno::class)->grupoMarca());
    }

    public function kitStore(Request $request): JsonResponse
    {
        $this->authorize('manageKit', Diseno::class);
        $data = $request->validate([
            'archivo' => 'required|file|mimes:jpg,jpeg,png,webp,gif|max:8192',
            'titulo' => 'nullable|string|max:120',
        ]);
        $file = $request->file('archivo');
        $path = MediosDiseno::guardarSubida($file, 'disenos/kit');
        $kit = DisenoKitAsset::query()->create([
            'titulo' => $data['titulo'] ?: (pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'Recurso de marca'),
            'path' => $path,
            'mime' => $file->getMimeType(),
            'bytes' => $file->getSize(),
            'user_id' => $request->user()->id,
        ]);

        return response()->json(['ok' => true, 'id' => $kit->id, 'url' => $kit->url()]);
    }

    public function kitDestroy(DisenoKitAsset $kit): JsonResponse
    {
        $this->authorize('manageKit', Diseno::class);
        MediosDiseno::borrar($kit->path);
        $kit->delete();

        return response()->json(['ok' => true]);
    }
}
