<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Biblioteca\BibliotecaService;
use App\Http\Controllers\Controller;
use App\Models\BibliotecaItem;
use App\Models\BibliotecaTag;
use App\Models\ProgramaRitmo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Biblioteca: consultar y buscar materiales, verlos o descargarlos, publicar (archivo
 * o enlace) y, con permiso de moderación, ocultar/publicar o eliminar. Mismas reglas
 * que el panel web (BibliotecaService).
 */
class BibliotecaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $modera = $this->modera($request);
        $query = BibliotecaItem::query()->with(['tags', 'toque:id,nombre,slug'])->latest();
        $estado = (string) $request->input('estado', 'publicado');
        if ($modera && in_array($estado, ['publicado', 'oculto', 'todos'], true)) {
            if ($estado !== 'todos') {
                $query->where('estado', $estado);
            }
        } else {
            $query->publicados();
        }
        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $query->where(fn ($w) => $w->where('titulo', 'like', "%{$q}%")->orWhere('descripcion', 'like', "%{$q}%")->orWhere('autor_nombre', 'like', "%{$q}%")
                ->orWhereHas('tags', fn ($t) => $t->where('nombre', 'like', '%'.BibliotecaTag::normalizarNombre($q).'%'))
                ->orWhereHas('toque', fn ($t) => $t->where('nombre', 'like', "%{$q}%")));
        }
        if ($request->filled('tag')) {
            $query->whereHas('tags', fn ($t) => $t->where('slug', $request->input('tag')));
        }
        if ($request->filled('tipo') && array_key_exists($request->input('tipo'), BibliotecaItem::TIPOS)) {
            $query->where('tipo', $request->input('tipo'));
        }
        if ($request->filled('toque')) {
            $query->whereHas('toque', fn ($t) => $t->where('slug', $request->input('toque')));
        }
        if ($request->filled('instrumento')) {
            $query->where('instrumento', $request->input('instrumento'));
        }
        $pagina = $query->paginate(30);

        return response()->json([
            'data' => collect($pagina->items())->map(fn (BibliotecaItem $i) => $this->item($i, $modera))->values(),
            'meta' => ['current_page' => $pagina->currentPage(), 'last_page' => $pagina->lastPage(), 'total' => $pagina->total()],
        ]);
    }

    public function show(Request $request, BibliotecaItem $item): JsonResponse
    {
        $modera = $this->modera($request);
        abort_unless($item->estado === 'publicado' || $modera, 404);

        return response()->json(['data' => $this->item($item->load(['tags', 'toque']), $modera)]);
    }

    public function archivo(Request $request, BibliotecaItem $item): StreamedResponse
    {
        abort_unless($item->estado === 'publicado' || $this->modera($request), 404);
        abort_unless($item->path && Storage::disk('comprobantes')->exists($item->path), 404, 'El archivo ya no está disponible.');
        $nombre = $item->nombre_original ?: 'material-'.$item->id;

        return Storage::disk('comprobantes')->response($item->path, $nombre, array_filter(['Content-Type' => $item->mime, 'Content-Disposition' => 'inline; filename="'.addslashes($nombre).'"']));
    }

    public function store(Request $request, BibliotecaService $servicio): JsonResponse
    {
        $datos = $request->validate($servicio->reglas(), $servicio->mensajes());
        $datos['autor_nombre'] ??= $request->user()->persona?->nombre_completo ?? $request->user()->name;
        $item = $servicio->publicar($datos, $request->file('archivo'), $request->ip());

        return response()->json(['data' => $this->item($item->load(['tags', 'toque']), $this->modera($request))], 201);
    }

    public function visibilidad(Request $request, BibliotecaItem $item, BibliotecaService $servicio): JsonResponse
    {
        abort_unless($this->modera($request), 403);

        return response()->json(['data' => $this->item($servicio->alternarVisibilidad($item)->load(['tags', 'toque']), true)]);
    }

    public function destroy(Request $request, BibliotecaItem $item, BibliotecaService $servicio): JsonResponse
    {
        abort_unless($this->modera($request), 403);
        $servicio->eliminar($item);

        return response()->json(['ok' => true]);
    }

    public function catalogo(Request $request): JsonResponse
    {
        return response()->json([
            'tipos' => BibliotecaItem::TIPOS,
            'instrumentos' => BibliotecaItem::instrumentosOpciones(),
            'toques' => Schema::hasTable('programa_ritmos')
                ? ProgramaRitmo::query()->when(Schema::hasColumn('programa_ritmos', 'publicado'), fn ($q) => $q->where('publicado', true))->orderBy('año')->orderBy('orden')->orderBy('nombre')->get(['id', 'nombre', 'slug'])
                : [],
            'tags' => BibliotecaTag::query()->orderByDesc('usos')->orderBy('nombre')->limit(30)->get(['nombre', 'slug', 'usos']),
            'modera' => $this->modera($request),
            'max_mb' => (int) (BibliotecaService::ARCHIVO_MAX_KB / 1024),
        ]);
    }

    /** Moderar requiere el permiso y ser administración (igual que el panel web). */
    private function modera(Request $request): bool
    {
        $u = $request->user();

        return $u->acceso()->puede('biblioteca.admin') && $u->isAdmin();
    }

    /** @return array<string, mixed> */
    private function item(BibliotecaItem $i, bool $modera): array
    {
        return [
            'id' => $i->id,
            'titulo' => $i->titulo,
            'descripcion' => $i->descripcion,
            'tipo' => $i->tipo,
            'tipo_nombre' => BibliotecaItem::TIPOS[$i->tipo] ?? $i->tipo,
            'autor' => $i->autor_nombre,
            'estado' => $i->estado,
            'tiene_archivo' => (bool) $i->path,
            'url' => $i->url,
            'mime' => $i->mime,
            'bytes' => $i->bytes,
            'nombre_original' => $i->nombre_original,
            'toque' => $i->toque ? ['nombre' => $i->toque->nombre, 'slug' => $i->toque->slug] : null,
            'instrumento' => $i->instrumento,
            'tags' => $i->tags->map(fn ($t) => ['nombre' => $t->nombre, 'slug' => $t->slug])->values(),
            'fecha' => $i->created_at?->toIso8601String(),
            'acciones' => ['moderar' => $modera],
        ];
    }
}
