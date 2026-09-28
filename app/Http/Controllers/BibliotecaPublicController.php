<?php

namespace App\Http\Controllers;

use App\Domain\Biblioteca\BibliotecaService;
use App\Models\BibliotecaItem;
use App\Models\BibliotecaTag;
use App\Models\ProgramaRitmo;
use App\Services\BibliotecaShareMiniatura;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BibliotecaPublicController extends Controller
{
    /** Límite de subida en KB (alineado con docker/php/uploads.ini). */
    private const ARCHIVO_MAX_KB = 102400;

    private const ARCHIVO_EXTENSIONES = 'jpg,jpeg,png,webp,gif,mp4,m4v,webm,mov,mp3,wav,ogg,m4a,pdf';

    public function index(Request $request)
    {
        if (! Schema::hasTable('biblioteca_items')) {
            return view('biblioteca.index', [
                'items' => collect(),
                'tagsPopulares' => collect(),
                'toques' => collect(),
                'instrumentos' => BibliotecaItem::instrumentosOpciones(),
                'q' => '',
                'tag' => null,
                'tipo' => '',
                'toqueSlug' => '',
                'toqueFiltro' => null,
                'instrumento' => '',
                'sinTabla' => true,
            ]);
        }

        $q = trim((string) $request->query('q', ''));
        $tagSlug = trim((string) $request->query('tag', ''));
        $tipo = trim((string) $request->query('tipo', ''));
        $toqueSlug = trim((string) $request->query('toque', ''));
        $instrumento = trim((string) $request->query('instrumento', ''));
        $instrumentos = BibliotecaItem::instrumentosOpciones();

        $query = BibliotecaItem::query()
            ->publicados()
            ->with(['tags', 'toque'])
            ->latest();

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('titulo', 'like', '%'.$q.'%')
                    ->orWhere('descripcion', 'like', '%'.$q.'%')
                    ->orWhere('autor_nombre', 'like', '%'.$q.'%')
                    ->orWhereHas('tags', function ($t) use ($q) {
                        $nombre = BibliotecaTag::normalizarNombre($q);
                        $t->where('nombre', 'like', '%'.$nombre.'%')
                            ->orWhere('slug', 'like', '%'.BibliotecaTag::slugFromNombre($nombre).'%');
                    })
                    ->orWhereHas('toque', function ($t) use ($q) {
                        $t->where('nombre', 'like', '%'.$q.'%')
                            ->orWhere('slug', 'like', '%'.$q.'%');
                    });
            });
        }

        $tag = null;
        if ($tagSlug !== '') {
            $tag = BibliotecaTag::query()->where('slug', $tagSlug)->first();
            if ($tag) {
                $query->whereHas('tags', fn ($t) => $t->where('biblioteca_tags.id', $tag->id));
            }
        }

        if ($tipo !== '' && array_key_exists($tipo, BibliotecaItem::TIPOS)) {
            $query->where('tipo', $tipo);
        }

        $toqueFiltro = null;
        if ($toqueSlug !== '' && Schema::hasColumn('biblioteca_items', 'programa_ritmo_id')) {
            $toqueFiltro = ProgramaRitmo::query()->where('slug', $toqueSlug)->first();
            if ($toqueFiltro) {
                $query->where('programa_ritmo_id', $toqueFiltro->id);
            }
        }

        if ($instrumento !== '' && array_key_exists($instrumento, $instrumentos)
            && Schema::hasColumn('biblioteca_items', 'instrumento')) {
            $query->where('instrumento', $instrumento);
        }

        $items = $query->paginate(36)->withQueryString();

        $tagsPopulares = BibliotecaTag::query()
            ->orderByDesc('usos')
            ->orderBy('nombre')
            ->limit(24)
            ->get();

        $toques = $this->toquesParaSelect();

        return view('biblioteca.index', compact(
            'items',
            'tagsPopulares',
            'toques',
            'instrumentos',
            'q',
            'tag',
            'tipo',
            'toqueSlug',
            'toqueFiltro',
            'instrumento'
        ) + ['sinTabla' => false]);
    }

    public function create(Request $request)
    {
        $toques = $this->toquesParaSelect();
        $instrumentos = BibliotecaItem::instrumentosOpciones();
        $toquePre = trim((string) $request->query('toque', old('toque', '')));
        $instrumentoPre = trim((string) $request->query('instrumento', old('instrumento', '')));

        return view('biblioteca.create', [
            'tagsPopulares' => Schema::hasTable('biblioteca_tags')
                ? BibliotecaTag::query()->orderByDesc('usos')->limit(16)->get()
                : collect(),
            'toques' => $toques,
            'instrumentos' => $instrumentos,
            'toquePre' => $toquePre,
            'instrumentoPre' => $instrumentoPre,
        ]);
    }

    public function store(Request $request)
    {
        if (! Schema::hasTable('biblioteca_items')) {
            return back()->withErrors(['archivo' => 'La biblioteca aún no está disponible. Corré las migraciones.'])->withInput();
        }

        // Honeypot anti-bot
        if (filled($request->input('website'))) {
            return redirect()->route('biblioteca.index')->with('success', '¡Gracias! Tu material se publicó.');
        }

        $errorSubida = $this->mensajeErrorSubida($request);
        if ($errorSubida !== null) {
            return back()->withErrors(['archivo' => $errorSubida])->withInput();
        }

        $servicio = app(BibliotecaService::class);
        $validated = $request->validate($servicio->reglas(), $servicio->mensajes());
        $item = $servicio->publicar($validated, $request->file('archivo'), $request->ip());

        $item->load('toque');
        if ($item->urlPasarAlEditor()) {
            return redirect()
                ->route('biblioteca.show', $item)
                ->with('success', 'Listo. Ya podés pasarlo al editor de partitura con el original al lado.');
        }

        $redirectParams = [];
        if (! empty($validated['toque'])) {
            $redirectParams['toque'] = $validated['toque'];
        }

        return redirect()
            ->route('biblioteca.index', $redirectParams)
            ->with('success', '¡Listo! Tu material ya está en la biblioteca.');
    }

    public function show(BibliotecaItem $bibliotecaItem)
    {
        $this->abortSiNoPublico($bibliotecaItem);
        $bibliotecaItem->load(['tags', 'toque']);

        return view('biblioteca.show', ['item' => $bibliotecaItem]);
    }

    public function miniatura(BibliotecaItem $bibliotecaItem, BibliotecaShareMiniatura $miniatura): StreamedResponse
    {
        $this->abortSiNoPublico($bibliotecaItem);

        return $miniatura->responder($bibliotecaItem);
    }

    public function archivo(BibliotecaItem $bibliotecaItem): StreamedResponse
    {
        $this->abortSiNoPublico($bibliotecaItem);
        if (! $bibliotecaItem->path || ! Storage::disk('comprobantes')->exists($bibliotecaItem->path)) {
            abort(404);
        }

        $nombre = $bibliotecaItem->nombre_original ?: ('material-'.$bibliotecaItem->id);
        $mime = $bibliotecaItem->mime;
        if (! $mime) {
            $ext = strtolower(pathinfo($bibliotecaItem->path, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'png' => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                'mp4', 'm4v' => 'video/mp4',
                'webm' => 'video/webm',
                'pdf' => 'application/pdf',
                default => null,
            };
        }

        $headers = [
            'Content-Disposition' => 'inline; filename="'.addslashes($nombre).'"',
            'Cache-Control' => 'public, max-age=86400',
        ];
        if ($mime) {
            $headers['Content-Type'] = $mime;
        }

        return Storage::disk('comprobantes')->response($bibliotecaItem->path, $nombre, $headers);
    }

    /**
     * JSON para ITO Diseño: imágenes publicadas de la Biblioteca (insertables en el canvas).
     */
    public function apiItems(Request $request)
    {
        if (! Schema::hasTable('biblioteca_items')) {
            return response()->json([
                'ok' => true,
                'data' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0, 'per_page' => 24],
                'tags' => [],
            ]);
        }

        $q = trim((string) $request->query('q', ''));
        $tagSlug = trim((string) $request->query('tag', ''));
        $tipo = trim((string) $request->query('tipo', 'imagen'));
        $perPage = min(48, max(12, (int) $request->query('per_page', 24)));

        $query = BibliotecaItem::query()
            ->publicados()
            ->with(['tags', 'toque'])
            ->latest();

        // Canvas: imágenes, o videos con miniatura usable como imagen.
        if ($tipo === 'canvas' || $tipo === 'media') {
            $query->where(function ($w) {
                $w->where(function ($img) {
                    $img->where(function ($t) {
                        $t->where('tipo', 'imagen')
                            ->orWhere('mime', 'like', 'image/%');
                    })->where(function ($src) {
                        $src->whereNotNull('path')->where('path', '!=', '')
                            ->orWhere(function ($u) {
                                $u->whereNotNull('url')->where('url', '!=', '');
                            });
                    });
                })->orWhere(function ($vid) {
                    $vid->where('tipo', 'video');
                });
            });
        } elseif ($tipo === 'imagen' || $tipo === '') {
            $query->where(function ($w) {
                $w->where('tipo', 'imagen')
                    ->orWhere('mime', 'like', 'image/%');
            })->where(function ($w) {
                $w->whereNotNull('path')->where('path', '!=', '')
                    ->orWhere(function ($u) {
                        $u->whereNotNull('url')->where('url', '!=', '');
                    });
            });
        } elseif (array_key_exists($tipo, BibliotecaItem::TIPOS)) {
            $query->where('tipo', $tipo);
        }

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('titulo', 'like', '%'.$q.'%')
                    ->orWhere('descripcion', 'like', '%'.$q.'%')
                    ->orWhere('autor_nombre', 'like', '%'.$q.'%')
                    ->orWhereHas('tags', function ($t) use ($q) {
                        $nombre = BibliotecaTag::normalizarNombre($q);
                        $t->where('nombre', 'like', '%'.$nombre.'%')
                            ->orWhere('slug', 'like', '%'.BibliotecaTag::slugFromNombre($nombre).'%');
                    });
            });
        }

        if ($tagSlug !== '') {
            $tag = BibliotecaTag::query()->where('slug', $tagSlug)->first();
            if ($tag) {
                $query->whereHas('tags', fn ($t) => $t->where('biblioteca_tags.id', $tag->id));
            }
        }

        $paginator = $query->paginate($perPage);

        $data = $paginator->getCollection()->map(function (BibliotecaItem $item) {
            $archivo = $item->archivoUrl();
            $mini = $item->miniaturaUrl();
            $isVideo = $item->tipo === 'video';
            // Videos: insertar miniatura en el lienzo (no el archivo de video).
            $insertUrl = $isVideo ? $mini : $archivo;
            if ($isVideo && ! $mini) {
                $insertUrl = null;
            }

            return [
                'id' => $item->id,
                'titulo' => $item->titulo,
                'tipo' => $item->tipo,
                'mime' => $item->mime,
                'archivo_url' => $archivo,
                'miniatura_url' => $mini,
                'thumb_url' => $isVideo ? $mini : ($item->path ? $archivo : $mini),
                'insert_url' => $insertUrl,
                'autor_nombre' => $item->autor_nombre,
                'tags' => $item->tags->pluck('nombre')->values()->all(),
                'toque' => $item->toque ? [
                    'slug' => $item->toque->slug,
                    'nombre' => $item->toque->nombre,
                ] : null,
            ];
        })->values();

        $tags = BibliotecaTag::query()
            ->orderByDesc('usos')
            ->orderBy('nombre')
            ->limit(20)
            ->get(['nombre', 'slug', 'usos'])
            ->map(fn (BibliotecaTag $t) => [
                'nombre' => $t->nombre,
                'slug' => $t->slug,
                'usos' => $t->usos,
            ]);

        return response()->json([
            'ok' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
            ],
            'tags' => $tags,
        ]);
    }

    private function mensajeErrorSubida(Request $request): ?string
    {
        $file = $request->files->get('archivo');
        if (! $file instanceof \Illuminate\Http\UploadedFile && ! $file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            return null;
        }
        if ($file->isValid()) {
            return null;
        }

        return match ($file->getError()) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El servidor rechazó el archivo por tamaño (máximo 100 MB). Comprimí el MP4 o pegá un enlace (YouTube, Drive, etc.).',
            UPLOAD_ERR_PARTIAL => 'La subida se interrumpió. Probá de nuevo con mejor conexión.',
            UPLOAD_ERR_NO_FILE => 'No se recibió el archivo. Elegí el MP4 de nuevo.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo guardar el archivo temporal. Reintentá en unos minutos.',
            default => 'No se pudo subir el archivo. Si es un MP4 grande, comprimilo a menos de 100 MB o pegá un enlace.',
        };
    }

    private function abortSiNoPublico(BibliotecaItem $item): void
    {
        if ($item->estado !== 'publicado' && ! auth()->user()?->isAdmin()) {
            abort(404);
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, ProgramaRitmo>
     */
    private function toquesParaSelect()
    {
        if (! Schema::hasTable('programa_ritmos')) {
            return collect();
        }

        $q = ProgramaRitmo::query()->orderBy('año')->orderBy('orden')->orderBy('nombre');
        if (Schema::hasColumn('programa_ritmos', 'publicado')) {
            $q->where(function ($w) {
                $w->where('publicado', true);
                if (auth()->user()?->isAdmin()) {
                    $w->orWhere('publicado', false);
                }
            });
        }

        return $q->get(['id', 'slug', 'nombre', 'año', 'orden']);
    }
}
