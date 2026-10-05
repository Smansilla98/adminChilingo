<?php

namespace App\Domain\Archivo;

use App\Models\ArchivoAcontecimiento;
use App\Models\ArchivoCapitulo;
use App\Models\ArchivoFoto;
use App\Models\ArchivoFotoPersona;
use App\Models\BibliotecaTag;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Lecturas del archivo publicado (web pública y API). Solo material publicado.
 */
class ArchivoConsultas
{
    /** Relaciones que necesita cualquier tarjeta de foto. */
    public const CON_FOTO = ['acontecimiento:id,titulo,slug,anio', 'sede:id,nombre'];

    /**
     * Años con material publicado, agrupados por década.
     *
     * @return array{decadas: list<array{decada: int, anios: list<array{anio: int, fotos: int, acontecimientos: int}>}>, total_fotos: int}
     */
    public function linea(): array
    {
        return Cache::remember('archivo:linea', 300, function () {
            $fotos = ArchivoFoto::query()->publicadas()->whereNotNull('anio')
                ->select('anio', DB::raw('count(*) as n'))->groupBy('anio')->pluck('n', 'anio');
            $acont = ArchivoAcontecimiento::query()->publicados()->whereNotNull('anio')
                ->select('anio', DB::raw('count(*) as n'))->groupBy('anio')->pluck('n', 'anio');
            $anios = $fotos->keys()->merge($acont->keys())->map('intval')->unique()->sort()->values();

            $decadas = $anios->groupBy(fn (int $a) => intdiv($a, 10) * 10)->map(fn ($lista, $decada) => [
                'decada' => (int) $decada,
                'anios' => $lista->map(fn (int $a) => ['anio' => $a, 'fotos' => (int) ($fotos[$a] ?? 0), 'acontecimientos' => (int) ($acont[$a] ?? 0)])->values()->all(),
            ])->values()->all();

            return ['decadas' => $decadas, 'total_fotos' => (int) ArchivoFoto::query()->publicadas()->count()];
        });
    }

    public static function olvidarCache(): void
    {
        Cache::forget('archivo:linea');
    }

    /**
     * Capítulos publicados en orden cronológico con sus acontecimientos y unas pocas
     * fotos de cada uno (límite por acontecimiento, sin N+1).
     *
     * @return Collection<int, ArchivoCapitulo>
     */
    public function capitulosConRelato(int $fotosPorAcontecimiento = 6): Collection
    {
        return ArchivoCapitulo::query()->publicados()->cronologico()
            ->with([
                'portada',
                'acontecimientos' => fn ($q) => $q->publicados()->with([
                    'portada',
                    'fotos' => fn ($f) => $f->publicadas()->limit($fotosPorAcontecimiento),
                ]),
            ])
            ->withCount(['fotos as fotos_publicadas_count' => fn ($q) => $q->publicadas()])
            ->get();
    }

    public function fotoDePortada(): ?ArchivoFoto
    {
        return ArchivoFoto::query()->publicadas()->whereNotNull('derivados')
            ->orderByDesc('destacada')->orderByRaw('anio IS NULL')->orderBy('anio')->orderBy('orden')
            ->first();
    }

    /** @return Collection<int, ArchivoFoto> */
    public function destacadas(int $limite = 12): Collection
    {
        return ArchivoFoto::query()->publicadas()->where('destacada', true)->with(self::CON_FOTO)
            ->cronologico()->limit($limite)->get();
    }

    /**
     * Búsqueda y filtros combinables sobre fotos publicadas.
     *
     * @param  array<string, mixed>  $f  q, decada, anio, desde, hasta, sede, tipo, tags[], persona, capitulo, acontecimiento
     */
    public function buscarFotos(array $f, int $porPagina = 30): LengthAwarePaginator
    {
        return $this->filtrar(ArchivoFoto::query()->publicadas(), $f)
            ->with(self::CON_FOTO)
            ->cronologico()
            ->paginate($porPagina)
            ->withQueryString();
    }

    /** @param  array<string, mixed>  $f */
    public function filtrar(Builder $query, array $f): Builder
    {
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function (Builder $w) use ($q, $like) {
                $w->where('titulo', 'like', $like)
                    ->orWhere('descripcion', 'like', $like)
                    ->orWhere('contexto', 'like', $like)
                    ->orWhere('lugar', 'like', $like)
                    ->orWhere('ciudad', 'like', $like)
                    ->orWhere('fotografo', 'like', $like)
                    ->orWhereHas('acontecimiento', fn ($a) => $a->where('titulo', 'like', $like))
                    ->orWhereHas('sede', fn ($s) => $s->where('nombre', 'like', $like))
                    ->orWhereHas('tags', fn ($t) => $t->where('nombre', 'like', '%'.BibliotecaTag::normalizarNombre($q).'%'))
                    ->orWhereHas('personas', fn ($p) => $p->where('nombre', 'like', $like)
                        ->orWhereHas('persona', fn ($pp) => $pp->buscar($q)));
                if (preg_match('/^(19|20)\d{2}$/', $q)) {
                    $w->orWhere('anio', (int) $q);
                }
            });
        }
        if (! empty($f['decada']) && preg_match('/^\d{4}$/', (string) $f['decada'])) {
            $d = (int) $f['decada'];
            $query->whereBetween('anio', [$d, $d + 9]);
        }
        if (! empty($f['anio'])) {
            $query->where('anio', (int) $f['anio']);
        }
        if (! empty($f['desde'])) {
            $query->where('anio', '>=', (int) $f['desde']);
        }
        if (! empty($f['hasta'])) {
            $query->where('anio', '<=', (int) $f['hasta']);
        }
        if (! empty($f['sede'])) {
            $query->where('sede_id', (int) $f['sede']);
        }
        if (! empty($f['tipo']) && array_key_exists($f['tipo'], ArchivoFoto::TIPOS)) {
            $query->where('tipo', $f['tipo']);
        }
        if (! empty($f['capitulo'])) {
            $query->where('capitulo_id', (int) $f['capitulo']);
        }
        if (! empty($f['acontecimiento'])) {
            $query->where('acontecimiento_id', (int) $f['acontecimiento']);
        }
        // Etiquetas: todas las elegidas (filtros combinados).
        foreach (array_slice(array_filter((array) ($f['tags'] ?? [])), 0, 6) as $slug) {
            $query->whereHas('tags', fn ($t) => $t->where('slug', (string) $slug));
        }
        if (! empty($f['persona'])) {
            $p = (string) $f['persona'];
            $query->whereHas('personas', fn ($w) => ctype_digit($p)
                ? $w->where('persona_id', (int) $p)
                : $w->where('nombre', $p));
        }

        return $query;
    }

    /** @return Collection<int, ArchivoAcontecimiento> */
    public function buscarAcontecimientos(string $q, int $limite = 8): Collection
    {
        $q = trim($q);
        if ($q === '') {
            return collect();
        }
        $like = '%'.$q.'%';

        return ArchivoAcontecimiento::query()->publicados()
            ->where(fn ($w) => $w->where('titulo', 'like', $like)->orWhere('descripcion', 'like', $like)
                ->orWhere('lugar', 'like', $like)->orWhere('ciudad', 'like', $like)
                ->when(preg_match('/^(19|20)\d{2}$/', $q), fn ($x) => $x->orWhere('anio', (int) $q)))
            ->with('portada')
            ->cronologico()->limit($limite)->get();
    }

    /**
     * Personas que aparecen en fotos publicadas, con cuántas veces. Solo el nombre.
     *
     * @return Collection<int, array{clave: string, nombre: string, fotos: int}>
     */
    public function personasPublicas(?string $q = null, int $limite = 40): Collection
    {
        $filas = ArchivoFotoPersona::query()
            ->whereHas('foto', fn ($f) => $f->publicadas())
            ->with('persona:id,nombre,apellido')
            ->get(['persona_id', 'nombre']);

        return $filas->groupBy(fn ($p) => $p->persona_id ? (string) $p->persona_id : 'n:'.mb_strtolower((string) $p->nombre))
            ->map(fn ($grupo) => [
                'clave' => $grupo->first()->persona_id ? (string) $grupo->first()->persona_id : (string) $grupo->first()->nombre,
                'persona_id' => $grupo->first()->persona_id,
                'nombre' => $grupo->first()->nombreVisible(),
                'fotos' => $grupo->count(),
            ])
            ->filter(fn ($p) => $p['nombre'] !== '' && (! $q || str_contains(mb_strtolower($p['nombre']), mb_strtolower($q))))
            ->sortByDesc('fotos')->take($limite)->values();
    }

    /**
     * Fotos que una cuenta puede ver en el backoffice: todo con alcance global; con
     * alcance de sede, las de sus sedes y las que cargó.
     */
    public function gestionables(User $user): Builder
    {
        $query = ArchivoFoto::query();
        $alcance = $user->acceso()->alcance('archivo.view')
            ->unir($user->acceso()->alcance('archivo.manage'))
            ->unir($user->acceso()->alcance('archivo.moderate'));
        if ($user->acceso()->esSuperadmin() || $alcance->esGlobal()) {
            return $query;
        }

        return $query->where(fn ($w) => $w->whereIn('sede_id', $alcance->sedeIds())->orWhere('aportada_por', $user->id));
    }

    /** @return array{pendiente: int, cambios: int, rechazada: int, aprobadas_hoy: int} */
    public function conteosModeracion(Builder $fotos): array
    {
        return [
            'pendiente' => (clone $fotos)->where('estado', 'pendiente')->count(),
            'cambios' => (clone $fotos)->where('estado', 'cambios')->count(),
            'rechazada' => (clone $fotos)->where('estado', 'rechazada')->count(),
            'aprobadas_hoy' => (clone $fotos)->whereNotNull('enviada_at')->where('estado', 'publicada')->where('revisada_at', '>=', now()->startOfDay())->count(),
        ];
    }

    /**
     * Foto anterior y siguiente en orden cronológico (para navegar la historia).
     *
     * @return array{0: ?ArchivoFoto, 1: ?ArchivoFoto}
     */
    public function vecinas(ArchivoFoto $foto): array
    {
        $lista = ArchivoFoto::query()->publicadas()->cronologico()->pluck('id')->all();
        $i = array_search($foto->id, $lista, true);
        if ($i === false) {
            return [null, null];
        }
        $ant = $i > 0 ? ArchivoFoto::query()->find($lista[$i - 1]) : null;
        $sig = $i < count($lista) - 1 ? ArchivoFoto::query()->find($lista[$i + 1]) : null;

        return [$ant, $sig];
    }
}
