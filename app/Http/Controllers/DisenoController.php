<?php

namespace App\Http\Controllers;

use App\Models\Diseno;
use App\Models\DisenoKitAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DisenoController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Diseno::class);

        $user = auth()->user();
        $query = Diseno::query()->with('user')->latest();

        if (! $user->isAdmin() && ! $user->isDireccion()) {
            // Propios + plantillas de estudio (lectura / base para duplicar)
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhereNull('user_id');
            });
        }

        $disenos = $query->paginate(12);

        return view('disenos.index', compact('disenos'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Diseno::class);

        $presets = [
            'flyer_feed' => ['formato' => 'flyer_feed', 'ancho' => 1080, 'alto' => 1350],
            'historia' => ['formato' => 'historia', 'ancho' => 1080, 'alto' => 1920],
            'post_cuadrado' => ['formato' => 'post_cuadrado', 'ancho' => 1080, 'alto' => 1080],
            'afiche_a4' => ['formato' => 'afiche_a4', 'ancho' => 1240, 'alto' => 1754],
            'flyer_a5' => ['formato' => 'flyer_a5', 'ancho' => 874, 'alto' => 1240],
            'banner_web' => ['formato' => 'banner_web', 'ancho' => 1200, 'alto' => 628],
            'hoodie_20x40' => ['formato' => 'hoodie_20x40', 'ancho' => 2362, 'alto' => 4724],
            'parche_10x10' => ['formato' => 'parche_10x10', 'ancho' => 1181, 'alto' => 1181],
            'custom' => ['formato' => 'custom', 'ancho' => 1080, 'alto' => 1080],
        ];
        $fmt = $request->query('formato', 'flyer_feed');
        $data = $presets[$fmt] ?? $presets['flyer_feed'];
        if ($request->filled('ancho') && $request->filled('alto')) {
            $data['ancho'] = max(200, min(8000, (int) $request->query('ancho')));
            $data['alto'] = max(200, min(8000, (int) $request->query('alto')));
            $data['formato'] = 'custom';
        }

        return view('disenos.form', [
            'diseno' => new Diseno($data),
            'brandAssets' => $this->brandAssetsCatalog(),
            'uploadUrl' => route('disenos.medios.store'),
            'bibliotecaApiUrl' => route('disenos.biblioteca.items'),
            'kitUploadUrl' => route('disenos.kit.store'),
            'kitListUrl' => route('disenos.kit.index'),
            'canManageKit' => auth()->user()?->can('manageKit', Diseno::class) ?? false,
            'canEdit' => true,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Diseno::class);

        $validated = $this->validateDiseno($request);
        $diseno = Diseno::create([
            ...$validated,
            'user_id' => auth()->id(),
        ]);
        $this->guardarPreview($diseno, $request->input('preview_base64'));

        return redirect()->route('disenos.index')->with('success', 'Diseño guardado.');
    }

    public function show(Diseno $diseno)
    {
        $this->authorize('view', $diseno);

        return redirect()->route('disenos.edit', $diseno);
    }

    public function edit(Diseno $diseno)
    {
        $this->authorize('view', $diseno);

        return view('disenos.form', [
            'diseno' => $diseno,
            'brandAssets' => $this->brandAssetsCatalog(),
            'uploadUrl' => route('disenos.medios.store'),
            'bibliotecaApiUrl' => route('disenos.biblioteca.items'),
            'kitUploadUrl' => route('disenos.kit.store'),
            'kitListUrl' => route('disenos.kit.index'),
            'canManageKit' => auth()->user()?->can('manageKit', Diseno::class) ?? false,
            'canEdit' => auth()->user()?->can('update', $diseno) ?? false,
        ]);
    }

    public function update(Request $request, Diseno $diseno)
    {
        $this->authorize('update', $diseno);

        $validated = $this->validateDiseno($request);
        $diseno->update($validated);
        $this->guardarPreview($diseno, $request->input('preview_base64'));

        return redirect()->route('disenos.index')->with('success', 'Diseño actualizado.');
    }

    public function destroy(Diseno $diseno)
    {
        $this->authorize('delete', $diseno);

        $this->borrarMediosDelDiseno($diseno);

        if ($diseno->preview_path) {
            Storage::disk('public')->delete($diseno->preview_path);
        }
        $diseno->delete();

        return redirect()->route('disenos.index')->with('success', 'Diseño eliminado.');
    }

    /**
     * Sube una imagen al disco público y devuelve URL estable (no data URL).
     */
    public function storeMedio(Request $request): JsonResponse
    {
        $this->authorize('uploadAsset', Diseno::class);

        $request->validate([
            'archivo' => 'required|file|mimes:jpg,jpeg,png,webp,gif,svg|max:8192',
        ]);

        $file = $request->file('archivo');
        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        $path = 'disenos/assets/'.auth()->id().'/'.Str::uuid().'.'.$ext;
        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

        return response()->json([
            'ok' => true,
            'path' => $path,
            'url' => asset('storage/'.$path),
            'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'Imagen',
        ]);
    }

    /** Kit de marca del estudio (logos/kits compartidos). */
    public function kitIndex(): JsonResponse
    {
        $this->authorize('uploadAsset', Diseno::class);

        if (! Schema::hasTable('diseno_kit_assets')) {
            return response()->json(['ok' => true, 'data' => []]);
        }

        $data = DisenoKitAsset::query()
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (DisenoKitAsset $a) => $a->toBrandCatalogItem())
            ->values();

        return response()->json(['ok' => true, 'data' => $data]);
    }

    public function kitStore(Request $request): JsonResponse
    {
        $this->authorize('manageKit', Diseno::class);

        if (! Schema::hasTable('diseno_kit_assets')) {
            return response()->json(['ok' => false, 'message' => 'Ejecutá migraciones para habilitar el kit de marca.'], 503);
        }

        $request->validate([
            'archivo' => 'required|file|mimes:jpg,jpeg,png,webp,gif,svg|max:8192',
            'titulo' => 'nullable|string|max:120',
        ]);

        $file = $request->file('archivo');
        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        $path = 'disenos/kit/'.Str::uuid().'.'.$ext;
        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

        $titulo = trim((string) $request->input('titulo', ''));
        if ($titulo === '') {
            $titulo = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'Asset de marca';
        }

        $asset = DisenoKitAsset::create([
            'titulo' => $titulo,
            'path' => $path,
            'mime' => $file->getMimeType(),
            'bytes' => $file->getSize() ?: null,
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'ok' => true,
            'item' => $asset->toBrandCatalogItem(),
        ]);
    }

    public function kitDestroy(DisenoKitAsset $kit): JsonResponse
    {
        $this->authorize('manageKit', Diseno::class);

        Storage::disk('public')->delete($kit->path);
        $kit->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Catálogo de logos oficiales + mockups + kit admin.
     *
     * @return list<array<string, mixed>>
     */
    private function brandAssetsCatalog(): array
    {
        $base = [
            [
                'id' => 'logo-oficial',
                'label' => 'Logo La Chilinga',
                'kind' => 'image',
                'url' => asset('images/diseno/brand/logo.png'),
                'thumb' => asset('images/diseno/brand/logo.png'),
            ],
            [
                'id' => 'logo-30',
                'label' => 'Logo 30 años',
                'kind' => 'image',
                'url' => asset('images/diseno/brand/chilinga-30.png'),
                'thumb' => asset('images/diseno/brand/chilinga-30.png'),
            ],
            [
                'id' => 'wordmark',
                'label' => 'Wordmark tipográfico',
                'kind' => 'text-badge',
                'text' => 'LA CHILINGA',
                'fontSize' => 48,
                'fontWeight' => '800',
            ],
            [
                'id' => 'bloque-naranja',
                'label' => 'Bloque naranja',
                'kind' => 'shape-rect',
                'fill' => '#f26422',
            ],
            [
                'id' => 'mockup-hoodie',
                'label' => 'Mockup hoodie',
                'kind' => 'mockup',
                'url' => asset('images/diseno/mockups/hoodie.svg'),
            ],
            [
                'id' => 'mockup-campera',
                'label' => 'Mockup campera',
                'kind' => 'mockup',
                'url' => asset('images/diseno/mockups/campera.svg'),
            ],
            [
                'id' => 'mockup-jersey',
                'label' => 'Mockup jersey',
                'kind' => 'mockup',
                'url' => asset('images/diseno/mockups/jersey.svg'),
            ],
            [
                'id' => 'mockup-tote',
                'label' => 'Mockup tote',
                'kind' => 'mockup',
                'url' => asset('images/diseno/mockups/tote.svg'),
            ],
        ];

        if (Schema::hasTable('diseno_kit_assets')) {
            $kit = DisenoKitAsset::query()
                ->latest()
                ->limit(80)
                ->get()
                ->map(fn (DisenoKitAsset $a) => $a->toBrandCatalogItem())
                ->all();
            $base = array_merge($base, $kit);
        }

        return $base;
    }

    private function validateDiseno(Request $request): array
    {
        $data = $request->validate([
            'titulo' => 'required|string|max:255',
            'formato' => 'required|string|max:40',
            'ancho' => 'required|integer|min:200|max:8000',
            'alto' => 'required|integer|min:200|max:8000',
            'canvas_json' => 'required|string',
            'preview_base64' => 'nullable|string',
        ]);

        json_decode($data['canvas_json'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            abort(422, 'JSON del canvas inválido.');
        }

        return [
            'titulo' => $data['titulo'],
            'formato' => $data['formato'],
            'ancho' => (int) $data['ancho'],
            'alto' => (int) $data['alto'],
            'canvas_json' => json_decode($data['canvas_json'], true),
        ];
    }

    private function guardarPreview(Diseno $diseno, ?string $base64): void
    {
        if (! $base64 || ! str_starts_with($base64, 'data:image')) {
            return;
        }

        $parts = explode(',', $base64, 2);
        if (count($parts) !== 2) {
            return;
        }

        $binary = base64_decode($parts[1], true);
        if ($binary === false) {
            return;
        }

        if ($diseno->preview_path) {
            Storage::disk('public')->delete($diseno->preview_path);
        }

        $path = 'disenos/previews/'.Str::uuid().'.png';
        Storage::disk('public')->put($path, $binary);
        $diseno->update(['preview_path' => $path]);
    }

    /**
     * Borra assets referenciados en el JSON si viven bajo disenos/assets/{user}/.
     */
    private function borrarMediosDelDiseno(Diseno $diseno): void
    {
        $json = $diseno->canvas_json;
        if (! is_array($json) || empty($json['objects']) || ! is_array($json['objects'])) {
            return;
        }
        foreach ($json['objects'] as $obj) {
            $src = $obj['src'] ?? null;
            if (! is_string($src) || $src === '') {
                continue;
            }
            $marker = '/storage/disenos/assets/';
            $pos = strpos($src, $marker);
            if ($pos === false) {
                continue;
            }
            $rel = 'disenos/assets/'.substr($src, $pos + strlen($marker));
            $rel = strtok($rel, '?') ?: $rel;
            if (str_starts_with($rel, 'disenos/assets/')) {
                Storage::disk('public')->delete($rel);
            }
        }
    }
}
