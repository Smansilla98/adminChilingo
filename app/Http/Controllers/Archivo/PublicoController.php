<?php

namespace App\Http\Controllers\Archivo;

use App\Domain\Archivo\ArchivoConsultas;
use App\Domain\Archivo\ImagenesArchivo;
use App\Domain\Archivo\PresentadorArchivo;
use App\Http\Controllers\Controller;
use App\Models\ArchivoAcontecimiento;
use App\Models\ArchivoCapitulo;
use App\Models\ArchivoFoto;
use App\Models\BibliotecaTag;
use App\Models\Sede;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Archivo histórico público: portada con línea de tiempo, Story Mode, años,
 * capítulos, acontecimientos, fichas de foto y búsqueda. Sin cuenta.
 */
class PublicoController extends Controller
{
    public function __construct(private readonly ArchivoConsultas $consultas) {}

    public function index(): View
    {
        $capitulos = $this->consultas->capitulosConRelato();
        $sueltos = ArchivoAcontecimiento::query()->publicados()->whereNull('capitulo_id')
            ->with(['portada', 'fotos' => fn ($q) => $q->publicadas()->limit(6)])
            ->cronologico()->get()->each(fn ($a) => $a->fotos->each->setRelation('acontecimiento', $a));
        $portada = $this->consultas->fotoDePortada();
        $linea = $this->consultas->linea();

        $fotosPagina = $this->fotosDe($capitulos, $sueltos);

        return view('archivo.index', [
            'capitulos' => $capitulos,
            'sueltos' => $sueltos,
            'portada' => $portada,
            'linea' => $linea,
            'destacadas' => $capitulos->isEmpty() && $sueltos->isEmpty() ? $this->consultas->destacadas(18) : collect(),
            'visor' => $fotosPagina,
            'seo' => $this->seo(
                'Archivo histórico de La Chilinga',
                'La memoria fotográfica de La Chilinga, navegable en el tiempo: capítulos, acontecimientos, lugares y personas.',
                $portada,
                route('archivo.index'),
            ),
        ]);
    }

    public function historia(): View
    {
        $capitulos = $this->consultas->capitulosConRelato(4);
        $portada = $this->consultas->fotoDePortada();

        return view('archivo.historia', [
            'capitulos' => $capitulos,
            'portada' => $portada,
            'visor' => $this->fotosDe($capitulos, collect()),
            'seo' => $this->seo('La historia de La Chilinga, capítulo a capítulo', 'Un recorrido documental por la historia de La Chilinga en fotografías.', $portada, route('archivo.historia')),
        ]);
    }

    public function anio(int $anio): View
    {
        $acontecimientos = ArchivoAcontecimiento::query()->publicados()->where('anio', $anio)
            ->with(['portada', 'capitulo', 'fotos' => fn ($q) => $q->publicadas()->limit(8)])
            ->cronologico()->get()->each(fn ($a) => $a->fotos->each->setRelation('acontecimiento', $a));
        $fotos = ArchivoFoto::query()->publicadas()->where('anio', $anio)->with(ArchivoConsultas::CON_FOTO)->cronologico()->get();
        abort_if($acontecimientos->isEmpty() && $fotos->isEmpty(), 404);

        $anios = collect($this->consultas->linea()['decadas'])->flatMap(fn ($d) => collect($d['anios'])->pluck('anio'))->values();
        $i = $anios->search($anio);

        return view('archivo.anio', [
            'anio' => $anio,
            'acontecimientos' => $acontecimientos,
            'fotos' => $fotos,
            'anterior' => $i !== false && $i > 0 ? $anios[$i - 1] : null,
            'siguiente' => $i !== false && $i < $anios->count() - 1 ? $anios[$i + 1] : null,
            'linea' => $this->consultas->linea(),
            'visor' => $fotos->map(fn ($f) => PresentadorArchivo::foto($f))->values(),
            'seo' => $this->seo("{$anio} en el archivo de La Chilinga", $fotos->count()." fotografías y {$acontecimientos->count()} acontecimientos de {$anio}.", $acontecimientos->first()?->portada ?? $fotos->first(), route('archivo.anio', $anio)),
        ]);
    }

    public function capitulo(string $slug): View
    {
        $capitulo = ArchivoCapitulo::query()->where('slug', $slug)->publicados()->with('portada')->firstOrFail();
        $acontecimientos = $capitulo->acontecimientos()->publicados()
            ->with(['portada', 'fotos' => fn ($q) => $q->publicadas()->limit(8)])->get()
            ->each(fn ($a) => $a->fotos->each->setRelation('acontecimiento', $a));
        $fotos = $capitulo->fotos()->publicadas()->with(ArchivoConsultas::CON_FOTO)->cronologico()->get();
        $todos = ArchivoCapitulo::query()->publicados()->cronologico()->get(['id', 'titulo', 'slug', 'anio_desde', 'anio_hasta']);
        $i = $todos->search(fn ($c) => $c->id === $capitulo->id);

        return view('archivo.capitulo', [
            'capitulo' => $capitulo,
            'acontecimientos' => $acontecimientos,
            'fotos' => $fotos,
            'numero' => $i === false ? null : $i + 1,
            'anterior' => $i ? $todos[$i - 1] : null,
            'siguiente' => $i !== false && $i < $todos->count() - 1 ? $todos[$i + 1] : null,
            'visor' => $fotos->map(fn ($f) => PresentadorArchivo::foto($f))->values(),
            'seo' => $this->seo($capitulo->titulo.' — Archivo de La Chilinga', $capitulo->bajada ?: Str::limit((string) $capitulo->descripcion, 160), $capitulo->portada ?? $fotos->first(), route('archivo.capitulo', $capitulo->slug)),
        ]);
    }

    public function acontecimiento(string $slug): View
    {
        $a = ArchivoAcontecimiento::query()->where('slug', $slug)->publicados()
            ->with(['portada', 'capitulo', 'sede', 'relacionados.portada', 'fotos' => fn ($q) => $q->publicadas()->with(ArchivoConsultas::CON_FOTO)])
            ->firstOrFail();
        $cronologia = ArchivoAcontecimiento::query()->publicados()->cronologico()->get(['id', 'titulo', 'slug', 'anio']);
        $i = $cronologia->search(fn ($x) => $x->id === $a->id);
        $portada = $a->portada?->esPublica() ? $a->portada : $a->fotos->first();

        return view('archivo.acontecimiento', [
            'a' => $a,
            'portada' => $portada,
            'anterior' => $i ? $cronologia[$i - 1] : null,
            'siguiente' => $i !== false && $i < $cronologia->count() - 1 ? $cronologia[$i + 1] : null,
            'visor' => $a->fotos->map(fn ($f) => PresentadorArchivo::foto($f))->values(),
            'seo' => $this->seo(
                $a->titulo.($a->anio ? " ({$a->anio})" : '').' — Archivo de La Chilinga',
                $a->bajada ?: Str::limit(strip_tags((string) ($a->descripcion ?: $a->relato)), 160),
                $portada,
                route('archivo.acontecimiento', $a->slug),
                'article',
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'Event',
                    'name' => $a->titulo,
                    'startDate' => $a->fecha?->toDateString() ?? (string) $a->anio,
                    'description' => $a->bajada ?: Str::limit(strip_tags((string) $a->descripcion), 300),
                    'location' => ($lugar = collect([$a->lugar, $a->ciudad, $a->pais])->filter()->implode(', ')) ? ['@type' => 'Place', 'name' => $lugar] : null,
                    'image' => $portada?->imagenUrl(1200),
                    'organizer' => ['@type' => 'Organization', 'name' => 'La Chilinga'],
                ],
            ),
        ]);
    }

    public function foto(string $slug): View
    {
        $foto = ArchivoFoto::query()->where('slug', $slug)->publicadas()
            ->with(['acontecimiento', 'capitulo', 'sede', 'tags', 'personas.persona', 'aportante'])
            ->firstOrFail();
        [$anterior, $siguiente] = $this->consultas->vecinas($foto);
        $relacionadas = ArchivoFoto::query()->publicadas()->whereKeyNot($foto->id)
            ->where(fn ($q) => $q->when($foto->acontecimiento_id, fn ($w) => $w->where('acontecimiento_id', $foto->acontecimiento_id))
                ->when(! $foto->acontecimiento_id && $foto->anio, fn ($w) => $w->where('anio', $foto->anio)))
            ->when(! $foto->acontecimiento_id && ! $foto->anio, fn ($q) => $q->whereRaw('1 = 0'))
            ->cronologico()->limit(12)->get();

        return view('archivo.foto', [
            'foto' => $foto,
            'datos' => PresentadorArchivo::foto($foto, true),
            'anterior' => $anterior,
            'siguiente' => $siguiente,
            'relacionadas' => $relacionadas,
            'visor' => collect([PresentadorArchivo::foto($foto)])->merge($relacionadas->map(fn ($f) => PresentadorArchivo::foto($f)))->values(),
            'seo' => $this->seo(
                $foto->tituloVisible().($foto->anio ? " ({$foto->anio})" : '').' — Archivo de La Chilinga',
                Str::limit((string) ($foto->descripcion ?: $foto->contexto ?: 'Fotografía del archivo histórico de La Chilinga.'), 160),
                $foto,
                route('archivo.foto', $foto->slug),
                'article',
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'Photograph',
                    'name' => $foto->tituloVisible(),
                    'dateCreated' => $foto->fecha?->toDateString() ?? ($foto->anio ? (string) $foto->anio : null),
                    'description' => $foto->descripcion,
                    'image' => $foto->imagenUrl(1200),
                    'creator' => $foto->fotografo ? ['@type' => 'Person', 'name' => $foto->fotografo] : null,
                    'creditText' => $foto->credito,
                    'license' => $foto->licencia,
                    'contentLocation' => ($l = collect([$foto->lugar, $foto->ciudad])->filter()->implode(', ')) ? ['@type' => 'Place', 'name' => $l] : null,
                ],
            ),
        ]);
    }

    public function buscar(Request $request): View
    {
        $f = [
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 120),
            'decada' => $request->query('decada'),
            'anio' => $request->query('anio'),
            'desde' => $request->query('desde'),
            'hasta' => $request->query('hasta'),
            'sede' => $request->query('sede'),
            'tipo' => $request->query('tipo'),
            'tags' => array_filter((array) $request->query('tags', [])),
            'persona' => $request->query('persona'),
        ];
        $fotos = $this->consultas->buscarFotos($f);
        $hayFiltros = collect($f)->filter(fn ($v) => $v !== null && $v !== '' && $v !== [])->isNotEmpty();

        return view('archivo.buscar', [
            'f' => $f,
            'hayFiltros' => $hayFiltros,
            'fotos' => $fotos,
            'historias' => $this->consultas->buscarAcontecimientos($f['q']),
            'linea' => $this->consultas->linea(),
            'sedes' => Sede::query()->whereIn('id', ArchivoFoto::query()->publicadas()->whereNotNull('sede_id')->distinct()->pluck('sede_id'))->orderBy('nombre')->get(['id', 'nombre']),
            'tags' => BibliotecaTag::query()->whereHas('archivoFotos', fn ($q) => $q->publicadas())
                ->withCount(['archivoFotos as usos_archivo' => fn ($q) => $q->publicadas()])
                ->orderByDesc('usos_archivo')->limit(40)->get(),
            'personas' => $this->consultas->personasPublicas(null, 30),
            'visor' => collect($fotos->items())->map(fn ($x) => PresentadorArchivo::foto($x))->values(),
            'seo' => $this->seo(
                $f['q'] !== '' ? '"'.$f['q'].'" en el archivo de La Chilinga' : 'Buscar en el archivo de La Chilinga',
                'Buscá fotos por año, lugar, persona, sede o etiqueta.',
                null,
                route('archivo.buscar'),
                'website',
                null,
                $hayFiltros, // las combinaciones de filtros no se indexan
            ),
        ]);
    }

    /** Personas que aparecen en fotos publicadas (autocompletar del aporte y filtros). */
    public function personas(Request $request): JsonResponse
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 60);

        return response()->json(['data' => $this->consultas->personasPublicas($q ?: null, 15)]);
    }

    public function imagen(Request $request, ArchivoFoto $foto, int $ancho, ImagenesArchivo $imagenes): Response
    {
        // Lo no publicado solo lo ve quien puede (aportante o equipo), con caché privada.
        if (! $foto->esPublica()) {
            abort_unless($request->user()?->can('view', $foto), 404);
        }

        return $imagenes->responder($foto, $ancho);
    }

    /**
     * Fotos de la página en orden de lectura, para el visor.
     *
     * @param  Collection<int, ArchivoCapitulo>  $capitulos
     * @param  Collection<int, ArchivoAcontecimiento>  $sueltos
     * @return Collection<int, array<string, mixed>>
     */
    private function fotosDe(Collection $capitulos, Collection $sueltos): Collection
    {
        return $capitulos->flatMap(fn ($c) => $c->acontecimientos)->merge($sueltos)
            ->flatMap(fn ($a) => $a->fotos)
            ->unique('id')
            ->map(fn ($f) => PresentadorArchivo::foto($f))
            ->values();
    }

    /**
     * @param  array<string, mixed>|null  $jsonld
     * @return array<string, mixed>
     */
    private function seo(string $titulo, ?string $descripcion, ?ArchivoFoto $imagen, string $url, string $tipo = 'website', ?array $jsonld = null, bool $noindex = false): array
    {
        return [
            'titulo' => $titulo,
            'descripcion' => $descripcion ?: 'Archivo histórico fotográfico de La Chilinga.',
            'imagen' => $imagen && $imagen->esPublica() && $imagen->derivados ? $imagen->imagenUrl(1200) : null,
            'imagen_alt' => $imagen?->textoAlternativo(),
            'url' => $url,
            'tipo' => $tipo,
            'jsonld' => $jsonld ? array_filter($jsonld, fn ($v) => $v !== null) : null,
            'noindex' => $noindex,
        ];
    }
}
