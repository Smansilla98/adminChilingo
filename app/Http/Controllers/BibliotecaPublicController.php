<?php

namespace App\Http\Controllers;

use App\Models\BibliotecaItem;
use App\Models\BibliotecaTag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BibliotecaPublicController extends Controller
{
    public function index(Request $request)
    {
        if (! Schema::hasTable('biblioteca_items')) {
            return view('biblioteca.index', [
                'items' => collect(),
                'tagsPopulares' => collect(),
                'q' => '',
                'tag' => null,
                'tipo' => '',
                'sinTabla' => true,
            ]);
        }

        $q = trim((string) $request->query('q', ''));
        $tagSlug = trim((string) $request->query('tag', ''));
        $tipo = trim((string) $request->query('tipo', ''));

        $query = BibliotecaItem::query()
            ->publicados()
            ->with('tags')
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

        $items = $query->paginate(36)->withQueryString();

        $tagsPopulares = BibliotecaTag::query()
            ->orderByDesc('usos')
            ->orderBy('nombre')
            ->limit(24)
            ->get();

        return view('biblioteca.index', compact('items', 'tagsPopulares', 'q', 'tag', 'tipo') + ['sinTabla' => false]);
    }

    public function create()
    {
        return view('biblioteca.create', [
            'tagsPopulares' => Schema::hasTable('biblioteca_tags')
                ? BibliotecaTag::query()->orderByDesc('usos')->limit(16)->get()
                : collect(),
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

        $validated = $request->validate([
            'titulo' => 'required|string|max:180',
            'descripcion' => 'nullable|string|max:2000',
            'autor_nombre' => 'nullable|string|max:120',
            'hashtags' => 'nullable|string|max:400',
            'url' => 'nullable|url|max:500',
            'archivo' => 'nullable|file|max:51200|mimes:jpg,jpeg,png,webp,gif,mp4,webm,mov,mp3,wav,ogg,m4a,pdf',
        ], [
            'archivo.max' => 'El archivo no puede superar 50 MB.',
            'archivo.mimes' => 'Formatos permitidos: PNG/JPG/WebP, MP4/WebM/MOV, audio o PDF.',
        ]);

        if (empty($validated['url']) && ! $request->hasFile('archivo')) {
            return back()->withErrors(['archivo' => 'Subí un archivo o pegá un enlace.'])->withInput();
        }

        $path = null;
        $mime = null;
        $nombreOriginal = null;
        $bytes = null;
        $ext = null;

        if ($request->hasFile('archivo')) {
            $file = $request->file('archivo');
            $ext = strtolower((string) $file->getClientOriginalExtension());
            // Preferir extensión real del cliente para no perder .png
            if ($ext === '' && $file->guessExtension()) {
                $ext = strtolower((string) $file->guessExtension());
            }
            $mime = $file->getMimeType() ?: $file->getClientMimeType();
            // Algunos browsers mandan application/octet-stream en PNG/MP4
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
            $file->storeAs($dir, $filename, 'comprobantes');
            $path = $dir.'/'.$filename;
        }

        $tipo = BibliotecaItem::detectarTipo($mime, $ext, ! empty($validated['url']));

        $item = BibliotecaItem::create([
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
        ]);

        $tags = BibliotecaTag::syncFromInput($validated['hashtags'] ?? '');
        if ($tags !== []) {
            $item->tags()->sync(collect($tags)->pluck('id')->all());
            foreach ($tags as $tag) {
                $tag->increment('usos');
            }
        }

        return redirect()
            ->route('biblioteca.index')
            ->with('success', '¡Listo! Tu material ya está en la biblioteca.');
    }

    public function archivo(BibliotecaItem $bibliotecaItem): StreamedResponse
    {
        if ($bibliotecaItem->estado !== 'publicado' && ! auth()->user()?->isAdmin()) {
            abort(404);
        }
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
}
