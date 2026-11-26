<?php

namespace App\Http\Controllers;

use App\Domain\Biblioteca\BibliotecaService;
use App\Domain\Biblioteca\CatalogoBiblioteca;
use App\Models\BibliotecaItem;
use App\Models\BibliotecaTag;
use App\Services\BibliotecaShareMiniatura;
use App\Support\ArchivoSeguro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BibliotecaPublicController extends Controller
{
    public function index(Request $request, CatalogoBiblioteca $catalogo)
    {
        return view('biblioteca.index', $catalogo->paginaPublica($request->query(), auth()->user()));
    }

    public function create(Request $request)
    {
        $toques = app(CatalogoBiblioteca::class)->toquesParaSelect(auth()->user());
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

        return ArchivoSeguro::responder('comprobantes', $bibliotecaItem->path, $nombre, true, ['Cache-Control' => 'public, max-age=86400']);
    }

    /**
     * JSON para ITO Diseño: imágenes publicadas de la Biblioteca (insertables en el canvas).
     */
    public function apiItems(Request $request, CatalogoBiblioteca $catalogo)
    {
        return response()->json($catalogo->catalogoDiseno($request->query()));
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
}
