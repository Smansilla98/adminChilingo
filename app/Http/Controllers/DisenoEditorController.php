<?php

namespace App\Http\Controllers;

use App\Models\BibliotecaItem;
use App\Models\Diseno;
use App\Models\DisenoKitAsset;
use App\Models\DisenoPagina;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Módulo Diseño con el editor OpenDesign (resources/opendesign, MIT).
 * Implementa en Laravel la API que el editor esperaba de su backend original (Hono/D1),
 * sobre las tablas `disenos` y `diseno_paginas`.
 */
class DisenoEditorController extends Controller
{
    /** Tamaños con nombre (se guardan en `formato` para reportes y compatibilidad). */
    private const FORMATOS = [
        '1080x1350' => 'flyer_feed',
        '1080x1920' => 'historia',
        '1080x1080' => 'post_cuadrado',
        '1240x1754' => 'afiche_a4',
        '874x1240' => 'flyer_a5',
        '1200x628' => 'banner_web',
        '2362x4724' => 'hoodie_20x40',
        '1181x1181' => 'parche_10x10',
    ];

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
        $canvas = $this->decodificar($data['canvas_json'] ?? '{}');
        $ancho = (int) ($data['width'] ?? 1080);
        $alto = (int) ($data['height'] ?? 1350);

        $diseno = DB::transaction(function () use ($data, $canvas, $ancho, $alto, $request) {
            $diseno = Diseno::query()->create([
                'titulo' => $data['name'] ?? 'Diseño sin título',
                'formato' => self::FORMATOS[$ancho.'x'.$alto] ?? 'custom',
                'ancho' => $ancho,
                'alto' => $alto,
                'canvas_json' => $canvas,
                'user_id' => $request->user()->id,
            ]);
            $diseno->paginas()->create([
                'titulo' => 'Página 1',
                'canvas_json' => $canvas ? json_encode($canvas) : '{}',
                'orden' => 0,
            ]);

            return $diseno;
        });

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

        $cambios = [];
        if (array_key_exists('name', $data) && filled($data['name'])) {
            $cambios['titulo'] = $data['name'];
        }
        if (array_key_exists('canvas_json', $data) && $data['canvas_json'] !== null) {
            $cambios['canvas_json'] = $this->decodificar($data['canvas_json']);
        }
        if (! empty($data['width']) && ! empty($data['height'])) {
            $cambios['ancho'] = (int) $data['width'];
            $cambios['alto'] = (int) $data['height'];
            $cambios['formato'] = self::FORMATOS[$data['width'].'x'.$data['height']] ?? 'custom';
        }
        if (! empty($data['thumbnail_data'])) {
            $cambios['preview_path'] = $this->guardarMiniatura($diseno, $data['thumbnail_data']) ?? $diseno->preview_path;
        }
        $diseno->fill($cambios)->save();

        return response()->json($diseno->fresh()->paraEditor());
    }

    public function destroy(Diseno $diseno): JsonResponse
    {
        $this->authorize('delete', $diseno);
        if ($diseno->preview_path) {
            Storage::disk('public')->delete($diseno->preview_path);
        }
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
        $this->decodificar($data['canvas_json'] ?? '{}');

        $pagina = DB::transaction(function () use ($diseno, $data) {
            if (isset($data['after_sort_order'])) {
                $diseno->paginas()->where('orden', '>', $data['after_sort_order'])->increment('orden');
                $orden = $data['after_sort_order'] + 1;
            } else {
                $orden = (int) ($diseno->paginas()->max('orden') ?? -1) + 1;
            }

            return $diseno->paginas()->create([
                'titulo' => $data['title'] ?? 'Página '.($diseno->paginas()->count() + 1),
                'canvas_json' => $data['canvas_json'] ?? '{}',
                'orden' => $orden,
            ]);
        });

        return response()->json($pagina->paraEditor());
    }

    public function duplicatePage(DisenoPagina $pagina): JsonResponse
    {
        $this->authorize('update', $pagina->diseno);

        $copia = DB::transaction(function () use ($pagina) {
            DisenoPagina::query()->where('diseno_id', $pagina->diseno_id)->where('orden', '>', $pagina->orden)->increment('orden');

            return DisenoPagina::query()->create([
                'diseno_id' => $pagina->diseno_id,
                'titulo' => Str::limit($pagina->titulo.' (copia)', 120, ''),
                'canvas_json' => $pagina->canvas_json,
                'orden' => $pagina->orden + 1,
            ]);
        });

        return response()->json($copia->paraEditor());
    }

    public function updatePage(Request $request, DisenoPagina $pagina): JsonResponse
    {
        $this->authorize('update', $pagina->diseno);
        $data = $request->validate([
            'title' => 'nullable|string|max:120',
            'canvas_json' => 'nullable|string|max:'.self::MAX_CANVAS,
        ]);
        if (isset($data['canvas_json'])) {
            $this->decodificar($data['canvas_json']);
            $pagina->canvas_json = $data['canvas_json'];
        }
        if (filled($data['title'] ?? null)) {
            $pagina->titulo = $data['title'];
        }
        $pagina->save();
        $pagina->diseno->touch();

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

        return response()->json($this->plantillas()->values());
    }

    public function template(string $id): JsonResponse
    {
        $this->authorize('viewAny', Diseno::class);
        $plantilla = $this->plantillas()->firstWhere('id', $id);

        return $plantilla ? response()->json($plantilla) : response()->json(['error' => 'No existe la plantilla.'], 404);
    }

    /**
     * Plantillas de La Chilinga + generales (database/data/disenos-plantillas.json) y
     * plantillas de estudio: diseños sin dueño del editor anterior (se copian, no se editan).
     */
    private function plantillas(): \Illuminate\Support\Collection
    {
        $fijas = collect(json_decode((string) file_get_contents(database_path('data/disenos-plantillas.json')), true) ?: [])
            ->map(fn (array $t) => $t + ['thumbnail_url' => null]);

        $estudio = Diseno::query()->whereNull('user_id')->latest()->limit(50)->get()->map(fn (Diseno $d) => [
            'id' => 'estudio-'.$d->id,
            'name' => $d->titulo,
            'category' => 'estudio',
            'canvas_json' => $d->canvas_json ? json_encode($d->canvas_json) : '{}',
            'width' => (int) $d->ancho,
            'height' => (int) $d->alto,
            'thumbnail_url' => $d->paraEditor()['thumbnail_url'],
            'sort_order' => 50,
        ]);

        return $fijas->concat($estudio)->sortBy('sort_order');
    }

    // ── Imágenes y marca ───────────────────────────────────────────────

    /** Subida de imagen para usar en el lienzo. SVG no: se sirve desde el mismo dominio. */
    public function upload(Request $request): JsonResponse
    {
        $this->authorize('uploadAsset', Diseno::class);
        $request->validate(['file' => 'required|file|mimes:jpg,jpeg,png,webp,gif|max:10240'], [
            'file.mimes' => 'Formato no admitido. Usá PNG, JPG, WebP o GIF.',
            'file.max' => 'La imagen supera los 10 MB.',
        ]);
        $file = $request->file('file');
        $path = $file->storeAs('disenos/medios/'.$request->user()->id, Str::uuid().'.'.$file->extension(), 'public');

        return response()->json(['url' => Storage::disk('public')->url($path)]);
    }

    /** Pestaña "Marca": logos, kit de marca, mockups y fotos de la Biblioteca. */
    public function marca(): JsonResponse
    {
        $this->authorize('uploadAsset', Diseno::class);

        $logos = [
            ['id' => 'logo', 'url' => asset('images/brand/logo.png'), 'label' => 'Logo ITO'],
            ['id' => 'logo-30', 'url' => asset('images/brand/chilinga-30.png'), 'label' => 'La Chilinga 30 años'],
        ];
        $mockups = collect(['hoodie', 'campera', 'jersey', 'tote'])
            ->filter(fn ($m) => is_file(public_path("images/diseno/mockups/$m.svg")))
            ->map(fn ($m) => ['id' => "mockup-$m", 'url' => asset("images/diseno/mockups/$m.svg"), 'label' => 'Mockup '.$m])
            ->values();

        $kit = Schema::hasTable('diseno_kit_assets')
            ? DisenoKitAsset::query()->latest()->limit(100)->get()->map(fn (DisenoKitAsset $a) => [
                'id' => 'kit-'.$a->id, 'url' => $a->url(), 'label' => $a->titulo, 'kit_id' => $a->id,
            ])->values()
            : collect();

        // Solo archivos propios (mismo dominio): una URL externa impediría exportar el PNG.
        $biblioteca = BibliotecaItem::query()->publicados()->whereNotNull('path')->latest()->limit(60)->get()
            ->filter(fn (BibliotecaItem $i) => $i->esImagen())
            ->map(fn (BibliotecaItem $i) => ['id' => 'bib-'.$i->id, 'url' => $i->archivoUrl(), 'label' => $i->titulo ?: 'Foto'])
            ->values();

        return response()->json(['grupos' => [
            ['clave' => 'logos', 'titulo' => 'Logos', 'items' => $logos],
            ['clave' => 'kit', 'titulo' => 'Kit de marca', 'items' => $kit],
            ['clave' => 'mockups', 'titulo' => 'Mockups', 'items' => $mockups],
            ['clave' => 'biblioteca', 'titulo' => 'Biblioteca', 'items' => $biblioteca],
        ]]);
    }

    public function kitStore(Request $request): JsonResponse
    {
        $this->authorize('manageKit', Diseno::class);
        $data = $request->validate([
            'archivo' => 'required|file|mimes:jpg,jpeg,png,webp,gif|max:8192',
            'titulo' => 'nullable|string|max:120',
        ]);
        $file = $request->file('archivo');
        $path = $file->storeAs('disenos/kit', Str::uuid().'.'.$file->extension(), 'public');
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
        Storage::disk('public')->delete($kit->path);
        $kit->delete();

        return response()->json(['ok' => true]);
    }

    // ── Auxiliares ─────────────────────────────────────────────────────

    /** @return array<string, mixed>|null */
    private function decodificar(?string $json): ?array
    {
        if ($json === null || trim($json) === '' || trim($json) === '{}') {
            return null;
        }
        $data = json_decode($json, true);
        if (! is_array($data)) {
            throw ValidationException::withMessages(['canvas_json' => 'El diseño recibido no es válido.']);
        }

        return $data;
    }

    private function guardarMiniatura(Diseno $diseno, string $dataUrl): ?string
    {
        if (! preg_match('#^data:image/(jpeg|png);base64,(.+)$#', $dataUrl, $m)) {
            return null;
        }
        $binario = base64_decode($m[2], true);
        if ($binario === false || strlen($binario) > 1_500_000 || @getimagesizefromstring($binario) === false) {
            return null;
        }
        $path = 'disenos/previews/'.$diseno->id.'.'.($m[1] === 'png' ? 'png' : 'jpg');
        if ($diseno->preview_path && $diseno->preview_path !== $path) {
            Storage::disk('public')->delete($diseno->preview_path);
        }
        Storage::disk('public')->put($path, $binario);

        return $path;
    }
}
