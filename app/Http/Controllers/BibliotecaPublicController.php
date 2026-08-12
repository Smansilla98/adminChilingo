<?php

namespace App\Http\Controllers;

use App\Models\BibliotecaItem;
use App\Models\BibliotecaTag;
use App\Models\ProgramaRitmo;
use App\Services\BibliotecaShareMiniatura;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

        $instrumentos = array_keys(BibliotecaItem::instrumentosOpciones());
        $tieneToqueCol = Schema::hasColumn('biblioteca_items', 'programa_ritmo_id');

        $rules = [
            'titulo' => 'required|string|max:180',
            'descripcion' => 'nullable|string|max:2000',
            'autor_nombre' => 'nullable|string|max:120',
            'hashtags' => 'nullable|string|max:400',
            'url' => 'nullable|url|max:500',
            'archivo' => 'nullable|file|max:'.self::ARCHIVO_MAX_KB.'|extensions:'.self::ARCHIVO_EXTENSIONES,
            'instrumento' => ['nullable', 'string', Rule::in($instrumentos)],
        ];

        if ($tieneToqueCol) {
            $rules['toque'] = [
                'nullable',
                'string',
                'max:120',
                Rule::exists('programa_ritmos', 'slug'),
            ];
        }

        $validated = $request->validate($rules, [
            'archivo.uploaded' => 'No se pudo subir el archivo. Si es un video, suele ser demasiado grande o se cortó la conexión. Máximo 100 MB, o pegá un enlace.',
            'archivo.max' => 'El archivo no puede superar 100 MB.',
            'archivo.extensions' => 'Formatos permitidos: PNG/JPG/WebP, MP4/WebM/MOV, audio o PDF.',
            'toque.exists' => 'Elegí un toque válido del programa.',
            'instrumento.in' => 'Elegí un instrumento de la lista.',
        ]);

        if (empty($validated['url']) && ! $request->hasFile('archivo')) {
            return back()->withErrors(['archivo' => 'Subí un archivo o pegá un enlace.'])->withInput();
        }

        // Instrumento sin toque no tiene sentido
        if (! empty($validated['instrumento']) && empty($validated['toque'] ?? null)) {
            return back()->withErrors(['toque' => 'Para indicar instrumento, elegí también el toque.'])->withInput();
        }

        $path = null;
        $mime = null;
        $nombreOriginal = null;
        $bytes = null;
        $ext = null;

        if ($request->hasFile('archivo')) {
            $file = $request->file('archivo');
            $ext = strtolower((string) $file->getClientOriginalExtension());
            if ($ext === '' && $file->guessExtension()) {
                $ext = strtolower((string) $file->guessExtension());
            }
            $mime = $file->getMimeType() ?: $file->getClientMimeType();
            if (($mime === 'application/octet-stream' || ! $mime) && $ext === 'png') {
                $mime = 'image/png';
            }
            if (($mime === 'application/octet-stream' || ! $mime) && in_array($ext, ['mp4', 'm4v', 'mov'], true)) {
                $mime = $ext === 'mov' ? 'video/quicktime' : 'video/mp4';
            }
            $nombreOriginal = $file->getClientOriginalName();
            $bytes = $file->getSize();
            $filename = (string) Str::uuid().($ext !== '' ? '.'.$ext : '');
            $dir = 'biblioteca/'.now()->format('Y/m');
            try {
                $stored = $file->storeAs($dir, $filename, 'comprobantes');
            } catch (\Throwable $e) {
                report($e);

                return back()->withErrors([
                    'archivo' => 'No se pudo guardar el archivo en el servidor. Reintentá o pegá un enlace.',
                ])->withInput();
            }
            if (! $stored) {
                return back()->withErrors([
                    'archivo' => 'No se pudo guardar el archivo en el volumen. Reintentá o pegá un enlace.',
                ])->withInput();
            }
            $path = $dir.'/'.$filename;
        }

        $tipo = BibliotecaItem::detectarTipo($mime, $ext, ! empty($validated['url']));

        $programaRitmoId = null;
        if ($tieneToqueCol && ! empty($validated['toque'])) {
            $programaRitmoId = ProgramaRitmo::query()
                ->where('slug', $validated['toque'])
                ->value('id');
        }

        $payload = [
            'titulo' => trim($validated['titulo']),
            'descripcion' => isset($validated['descripcion']) ? trim($validated['descripcion']) : null,
            'tipo' => $tipo,
            'path' => $path,
            'url' => $validated['url'] ?? null,
            'mime' => $mime,
            'nombre_original' => $nombreOriginal,
            'bytes' => $bytes,
            'autor_nombre' => isset($validated['autor_nombre']) ? trim($validated['autor_nombre']) : null,
            'estado' => 'publicado',
            'ip' => $request->ip(),
        ];

        if ($tieneToqueCol) {
            $payload['programa_ritmo_id'] = $programaRitmoId;
            $payload['instrumento'] = $programaRitmoId
                ? ($validated['instrumento'] ?? null)
                : null;
        }

        $item = BibliotecaItem::create($payload);

        $tags = BibliotecaTag::syncFromInput($validated['hashtags'] ?? '');
        if ($tags !== []) {
            $item->tags()->sync(collect($tags)->pluck('id')->all());
            foreach ($tags as $tag) {
                $tag->increment('usos');
            }
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
