<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Archivo\ArchivoConsultas;
use App\Domain\Archivo\ArchivoService;
use App\Domain\Archivo\ImagenesArchivo;
use App\Domain\Archivo\PresentadorArchivo;
use App\Http\Controllers\Controller;
use App\Models\ArchivoAcontecimiento;
use App\Models\ArchivoCapitulo;
use App\Models\ArchivoFoto;
use App\Models\Sede;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Archivo histórico por API: lectura del material publicado (sin cuenta) y aportes
 * de la comunidad (con cuenta). Mismos servicios y Policies que la web.
 */
class ArchivoController extends Controller
{
    public function __construct(
        private readonly ArchivoConsultas $consultas,
        private readonly ArchivoService $archivo,
    ) {}

    // ── Lectura pública ──────────────────────────────────────────────────────

    public function index(): JsonResponse
    {
        $portada = $this->consultas->fotoDePortada();

        return response()->json([
            'portada' => $portada ? PresentadorArchivo::foto($portada) : null,
            'linea' => $this->consultas->linea(),
            'capitulos' => ArchivoCapitulo::query()->publicados()->cronologico()->with('portada')->get()
                ->map(fn ($c) => PresentadorArchivo::capitulo($c))->values(),
        ]);
    }

    public function linea(): JsonResponse
    {
        return response()->json($this->consultas->linea());
    }

    public function capitulos(): JsonResponse
    {
        return response()->json(['data' => ArchivoCapitulo::query()->publicados()->cronologico()->with('portada')->get()
            ->map(fn ($c) => PresentadorArchivo::capitulo($c))->values()]);
    }

    public function capitulo(string $slug): JsonResponse
    {
        $c = ArchivoCapitulo::query()->where('slug', $slug)->publicados()->with('portada')->firstOrFail();
        $acontecimientos = $c->acontecimientos()->publicados()->with(['portada', 'fotos' => fn ($q) => $q->publicadas()->limit(8)])->get();

        return response()->json(['data' => PresentadorArchivo::capitulo($c) + [
            'acontecimientos' => $acontecimientos->map(fn ($a) => PresentadorArchivo::acontecimiento($a) + [
                'fotos' => $a->fotos->map(fn ($f) => PresentadorArchivo::foto($f))->values(),
            ])->values(),
        ]]);
    }

    public function acontecimientos(Request $request): JsonResponse
    {
        $q = ArchivoAcontecimiento::query()->publicados()->with('portada')->cronologico()
            ->when($request->integer('anio'), fn ($w, $a) => $w->where('anio', $a))
            ->when($request->integer('capitulo'), fn ($w, $c) => $w->where('capitulo_id', $c))
            ->when(trim((string) $request->query('q')), fn ($w, $t) => $w->where('titulo', 'like', '%'.$t.'%'));

        return $this->paginado($q->paginate(30), fn ($a) => PresentadorArchivo::acontecimiento($a));
    }

    public function acontecimiento(string $slug): JsonResponse
    {
        $a = ArchivoAcontecimiento::query()->where('slug', $slug)->publicados()
            ->with(['portada', 'capitulo', 'sede', 'relacionados.portada', 'fotos' => fn ($q) => $q->publicadas()->with(ArchivoConsultas::CON_FOTO)])
            ->firstOrFail();

        return response()->json(['data' => PresentadorArchivo::acontecimiento($a, true)]);
    }

    /** Búsqueda y filtros: q, decada, anio, desde, hasta, sede, tipo, tags[], persona, capitulo, acontecimiento. */
    public function fotos(Request $request): JsonResponse
    {
        $filtros = $request->only(['q', 'decada', 'anio', 'desde', 'hasta', 'sede', 'tipo', 'persona', 'capitulo', 'acontecimiento']);
        $filtros['tags'] = array_filter((array) $request->query('tags', []));

        return $this->paginado($this->consultas->buscarFotos($filtros), fn ($f) => PresentadorArchivo::foto($f));
    }

    public function foto(Request $request, string $foto): JsonResponse
    {
        $f = ArchivoFoto::query()->where(ctype_digit($foto) ? 'id' : 'slug', $foto)
            ->with(['acontecimiento', 'capitulo', 'sede', 'tags', 'personas.persona', 'aportante'])->firstOrFail();
        $user = $request->user('sanctum');
        abort_unless($f->esPublica() || ($user && $user->can('view', $f)), 404);

        return response()->json(['data' => $user && ! $f->esPublica()
            ? PresentadorArchivo::fotoGestion($f->load('revisiones.user'), $user)
            : PresentadorArchivo::foto($f, true)]);
    }

    public function personas(Request $request): JsonResponse
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 60);

        return response()->json(['data' => $this->consultas->personasPublicas($q ?: null, 20)]);
    }

    /** Derivado de imagen con token: para material no publicado (aportes, moderación). */
    public function imagen(Request $request, ArchivoFoto $foto, int $ancho, ImagenesArchivo $imagenes): Response
    {
        abort_unless($foto->esPublica() || $request->user()->can('view', $foto), 404);

        return $imagenes->responder($foto, $ancho);
    }

    // ── Aportes de la comunidad ──────────────────────────────────────────────

    /** Opciones para los formularios y qué puede hacer la cuenta. */
    public function catalogo(Request $request): JsonResponse
    {
        $acceso = $request->user()->acceso();

        return response()->json([
            'tipos' => ArchivoFoto::TIPOS,
            'fuentes' => ArchivoFoto::FUENTES,
            'precisiones' => ArchivoFoto::PRECISIONES,
            'estados' => ArchivoFoto::ESTADOS,
            'sedes' => Sede::query()->orderBy('nombre')->get(['id', 'nombre']),
            'capitulos' => ArchivoCapitulo::query()->cronologico()->get(['id', 'titulo', 'publicado']),
            'acontecimientos' => ArchivoAcontecimiento::query()->cronologico()->get(['id', 'titulo', 'anio', 'publicado']),
            'max_mb' => intdiv(ImagenesArchivo::MAX_KB, 1024),
            'permisos' => [
                'aportar' => $request->user()->can('aportar', ArchivoFoto::class),
                'gestionar' => $acceso->puedeAlguno(['archivo.view', 'archivo.manage', 'archivo.moderate']),
                'subir' => $acceso->puede('archivo.manage'),
                'moderar' => $acceso->puede('archivo.moderate'),
                'publicar' => $acceso->puede('archivo.publish'),
                'capitulos' => $acceso->puedeGlobal('archivo.manage'),
            ],
        ]);
    }

    /**
     * Sube un aporte (multipart). Con datos y `enviar=1` queda en revisión de una vez.
     * Si la imagen ya está en el archivo responde 409 salvo `confirmar_duplicado=1`.
     */
    public function aportar(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorize('aportar', ArchivoFoto::class);
        $datos = $request->validate($this->archivo->reglasArchivo() + $this->archivo->reglasMetadatos(false) + [
            'confirmar_duplicado' => 'nullable|boolean',
            'enviar' => 'nullable|boolean',
        ], $this->archivo->mensajes());

        if (! $request->boolean('confirmar_duplicado')) {
            $iguales = $this->archivo->duplicados(ImagenesArchivo::hashDe($request->file('archivo')));
            if ($iguales->isNotEmpty()) {
                return response()->json([
                    'message' => 'Esta fotografía podría ya formar parte del archivo.',
                    'duplicados' => $iguales->map(fn ($f) => ['id' => $f->id, 'titulo' => $f->esPublica() ? $f->tituloVisible() : 'Foto en revisión', 'url' => $f->esPublica() ? $f->url() : null])->values(),
                ], 409);
            }
        }

        $metadatos = collect($datos)->except(['archivo', 'confirmar_duplicado', 'enviar'])->all();
        $metadatos['mostrar_aportante'] = $request->boolean('mostrar_aportante');
        $foto = $this->archivo->subir($request->file('archivo'), $metadatos, $user, false);
        if ($request->boolean('enviar')) {
            $foto = $this->archivo->enviar($foto, $user);
        }

        return response()->json(['data' => PresentadorArchivo::fotoGestion($foto->fresh(['tags', 'personas.persona', 'revisiones.user']), $user)], 201);
    }

    public function misAportes(Request $request): JsonResponse
    {
        $estado = $request->query('estado');
        $base = ArchivoFoto::query()->where('aportada_por', $request->user()->id);
        $pagina = (clone $base)
            ->when($estado && array_key_exists($estado, ArchivoFoto::ESTADOS), fn ($q) => $q->where('estado', $estado))
            ->latest('id')->paginate(30);

        return response()->json([
            'data' => collect($pagina->items())->map(fn (ArchivoFoto $f) => PresentadorArchivo::foto($f, false, true) + [
                'estado' => $f->estado,
                'estado_etiqueta' => $f->etiquetaEstado(),
                'notas_revision' => $f->estado === 'cambios' ? $f->notas_revision : null,
            ])->values(),
            'meta' => [
                'current_page' => $pagina->currentPage(), 'last_page' => $pagina->lastPage(), 'total' => $pagina->total(),
                'conteos' => (clone $base)->selectRaw('estado, count(*) as n')->groupBy('estado')->pluck('n', 'estado'),
            ],
        ]);
    }

    public function aporte(Request $request, ArchivoFoto $foto): JsonResponse
    {
        abort_unless((int) $foto->aportada_por === (int) $request->user()->id, 404);

        return response()->json(['data' => PresentadorArchivo::fotoGestion($foto->load(['tags', 'personas.persona', 'revisiones.user']), $request->user())]);
    }

    public function actualizarAporte(Request $request, ArchivoFoto $foto): JsonResponse
    {
        abort_unless((int) $foto->aportada_por === (int) $request->user()->id, 404);
        $this->authorize('update', $foto);
        $datos = $request->validate($this->archivo->reglasMetadatos(false) + ['enviar' => 'nullable|boolean'], $this->archivo->mensajes());
        if ($request->has('mostrar_aportante')) {
            $datos['mostrar_aportante'] = $request->boolean('mostrar_aportante');
        }
        $foto = $this->archivo->actualizar($foto, collect($datos)->except('enviar')->all(), $request->user(), false);
        if ($request->boolean('enviar')) {
            $this->authorize('enviar', $foto);
            $foto = $this->archivo->enviar($foto, $request->user());
        }

        return response()->json(['data' => PresentadorArchivo::fotoGestion($foto->fresh(['tags', 'personas.persona', 'revisiones.user']), $request->user())]);
    }

    public function enviarAporte(Request $request, ArchivoFoto $foto): JsonResponse
    {
        $this->authorize('enviar', $foto);
        $foto = $this->archivo->enviar($foto, $request->user());

        return response()->json(['data' => PresentadorArchivo::fotoGestion($foto->fresh(['tags', 'personas.persona', 'revisiones.user']), $request->user())]);
    }

    public function eliminarAporte(Request $request, ArchivoFoto $foto): JsonResponse
    {
        abort_unless((int) $foto->aportada_por === (int) $request->user()->id, 404);
        $this->authorize('delete', $foto);
        $this->archivo->eliminar($foto);

        return response()->json(['ok' => true]);
    }

    /** @param  callable(mixed): array<string, mixed>  $map */
    private function paginado(LengthAwarePaginator $pagina, callable $map): JsonResponse
    {
        return response()->json([
            'data' => collect($pagina->items())->map($map)->values(),
            'meta' => ['current_page' => $pagina->currentPage(), 'last_page' => $pagina->lastPage(), 'total' => $pagina->total()],
        ]);
    }
}
