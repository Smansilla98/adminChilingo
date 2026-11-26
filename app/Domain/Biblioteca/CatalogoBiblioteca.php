<?php

namespace App\Domain\Biblioteca;

use App\Models\BibliotecaItem;
use App\Models\BibliotecaTag;
use App\Models\ProgramaRitmo;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Consultas públicas de la Biblioteca: listado con filtros, catálogo para el editor de diseño
 * y toques para los selectores. El controlador valida acceso y responde.
 */
class CatalogoBiblioteca
{
    /**
     * Datos de la vista pública /biblioteca.
     *
     * @param  array<string, mixed>  $entrada  Parámetros de la query string.
     * @return array<string, mixed>
     */
    public function paginaPublica(array $entrada, ?User $user): array
    {
        $instrumentos = BibliotecaItem::instrumentosOpciones();
        if (! Schema::hasTable('biblioteca_items')) {
            return [
                'items' => collect(),
                'tagsPopulares' => collect(),
                'toques' => collect(),
                'instrumentos' => $instrumentos,
                'q' => '',
                'tag' => null,
                'tipo' => '',
                'toqueSlug' => '',
                'toqueFiltro' => null,
                'instrumento' => '',
                'sinTabla' => true,
            ];
        }

        $q = trim((string) ($entrada['q'] ?? ''));
        $tagSlug = trim((string) ($entrada['tag'] ?? ''));
        $tipo = trim((string) ($entrada['tipo'] ?? ''));
        $toqueSlug = trim((string) ($entrada['toque'] ?? ''));
        $instrumento = trim((string) ($entrada['instrumento'] ?? ''));

        $query = BibliotecaItem::query()
            ->publicados()
            ->with(['tags', 'toque'])
            ->latest();

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $this->aplicarBusquedaTexto($w, $q);
                $w->orWhereHas('toque', function ($t) use ($q) {
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

        return [
            'items' => $query->paginate(36)->withQueryString(),
            'tagsPopulares' => BibliotecaTag::query()->orderByDesc('usos')->orderBy('nombre')->limit(24)->get(),
            'toques' => $this->toquesParaSelect($user),
            'instrumentos' => $instrumentos,
            'q' => $q,
            'tag' => $tag,
            'tipo' => $tipo,
            'toqueSlug' => $toqueSlug,
            'toqueFiltro' => $toqueFiltro,
            'instrumento' => $instrumento,
            'sinTabla' => false,
        ];
    }

    /**
     * Respuesta JSON para ITO Diseño: imágenes publicadas (insertables en el canvas).
     *
     * @param  array<string, mixed>  $entrada  Parámetros de la query string.
     * @return array<string, mixed>
     */
    public function catalogoDiseno(array $entrada): array
    {
        if (! Schema::hasTable('biblioteca_items')) {
            return [
                'ok' => true,
                'data' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0, 'per_page' => 24],
                'tags' => [],
            ];
        }

        $q = trim((string) ($entrada['q'] ?? ''));
        $tagSlug = trim((string) ($entrada['tag'] ?? ''));
        $tipo = trim((string) ($entrada['tipo'] ?? 'imagen'));
        $perPage = min(48, max(12, (int) ($entrada['per_page'] ?? 24)));

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
                    })->where(fn ($src) => $this->conFuenteSubida($src));
                })->orWhere(function ($vid) {
                    $vid->where('tipo', 'video');
                });
            });
        } elseif ($tipo === 'imagen' || $tipo === '') {
            $query->where(function ($w) {
                $w->where('tipo', 'imagen')
                    ->orWhere('mime', 'like', 'image/%');
            })->where(fn ($w) => $this->conFuenteSubida($w));
        } elseif (array_key_exists($tipo, BibliotecaItem::TIPOS)) {
            $query->where('tipo', $tipo);
        }

        if ($q !== '') {
            $query->where(fn ($w) => $this->aplicarBusquedaTexto($w, $q));
        }

        if ($tagSlug !== '') {
            $tag = BibliotecaTag::query()->where('slug', $tagSlug)->first();
            if ($tag) {
                $query->whereHas('tags', fn ($t) => $t->where('biblioteca_tags.id', $tag->id));
            }
        }

        $paginator = $query->paginate($perPage);

        $data = $paginator->getCollection()->map(fn (BibliotecaItem $item) => $this->filaDiseno($item))->values();

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

        return [
            'ok' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
            ],
            'tags' => $tags,
        ];
    }

    /**
     * Toques publicados para el selector (los borradores sólo los ve el admin).
     *
     * @return \Illuminate\Support\Collection<int, ProgramaRitmo>
     */
    public function toquesParaSelect(?User $user)
    {
        if (! Schema::hasTable('programa_ritmos')) {
            return collect();
        }

        $q = ProgramaRitmo::query()->orderBy('año')->orderBy('orden')->orderBy('nombre');
        if (Schema::hasColumn('programa_ritmos', 'publicado')) {
            $q->where(function ($w) use ($user) {
                $w->where('publicado', true);
                if ($user?->isAdmin()) {
                    $w->orWhere('publicado', false);
                }
            });
        }

        return $q->get(['id', 'slug', 'nombre', 'año', 'orden']);
    }

    /** Búsqueda por texto en título, descripción, autor y etiquetas. */
    private function aplicarBusquedaTexto(Builder $w, string $q): void
    {
        $w->where('titulo', 'like', '%'.$q.'%')
            ->orWhere('descripcion', 'like', '%'.$q.'%')
            ->orWhere('autor_nombre', 'like', '%'.$q.'%')
            ->orWhereHas('tags', function ($t) use ($q) {
                $nombre = BibliotecaTag::normalizarNombre($q);
                $t->where('nombre', 'like', '%'.$nombre.'%')
                    ->orWhere('slug', 'like', '%'.BibliotecaTag::slugFromNombre($nombre).'%');
            });
    }

    /** Archivo subido o, en su defecto, URL externa. */
    private function conFuenteSubida(Builder $w): void
    {
        $w->where(function ($src) {
            $src->whereNotNull('path')->where('path', '!=', '')
                ->orWhere(function ($u) {
                    $u->whereNotNull('url')->where('url', '!=', '');
                });
        });
    }

    /** @return array<string, mixed> */
    private function filaDiseno(BibliotecaItem $item): array
    {
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
    }
}
