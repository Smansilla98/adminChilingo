<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Archivo\ArchivoConsultas;
use App\Domain\Archivo\ArchivoEditorialService;
use App\Domain\Archivo\ArchivoService;
use App\Domain\Archivo\ImagenesArchivo;
use App\Domain\Archivo\PresentadorArchivo;
use App\Http\Controllers\Controller;
use App\Models\ArchivoAcontecimiento;
use App\Models\ArchivoCapitulo;
use App\Models\ArchivoFoto;
use App\Models\ArchivoFotoPersona;
use App\Models\Persona;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Gestión del archivo por API (app móvil): tablero, fotos, carga, edición, lote,
 * orden, moderación, capítulos y acontecimientos. Requiere archivo.* y cada
 * registro lo decide su Policy, igual que en la web.
 */
class ArchivoGestionController extends Controller
{
    public function __construct(
        private readonly ArchivoService $archivo,
        private readonly ArchivoConsultas $consultas,
        private readonly ArchivoEditorialService $editorial,
    ) {}

    public function resumen(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ArchivoFoto::class);
        $fotos = $this->consultas->gestionables($request->user());

        return response()->json([
            'totales' => [
                'fotos' => (clone $fotos)->count(),
                'publicadas' => (clone $fotos)->publicadas()->count(),
                'capitulos' => ArchivoCapitulo::query()->count(),
                'acontecimientos' => ArchivoAcontecimiento::query()->count(),
            ],
            'moderacion' => $this->consultas->conteosModeracion($fotos),
            'calidad' => [
                'sin_fecha' => (clone $fotos)->whereNull('anio')->where('estado', '!=', 'rechazada')->count(),
                'sin_credito' => (clone $fotos)->whereNull('fotografo')->whereNull('credito')->where('estado', '!=', 'rechazada')->count(),
                'sin_descripcion' => (clone $fotos)->whereNull('descripcion')->where('estado', '!=', 'rechazada')->count(),
                'borradores' => (clone $fotos)->where('estado', 'borrador')->count(),
            ],
        ]);
    }

    /** Fotos del backoffice. Filtros: q, estado, decada, anio, sin, capitulo, acontecimiento, sede, tipo, tag. */
    public function fotos(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ArchivoFoto::class);
        $query = $this->consultas->gestionables($request->user())->with(['acontecimiento:id,titulo,slug,publicado', 'sede:id,nombre']);
        $this->consultas->filtrar($query, $request->only(['q', 'decada', 'anio', 'capitulo', 'acontecimiento', 'sede', 'tipo']) + ['tags' => array_filter([$request->query('tag')])]);
        if ($request->filled('estado') && array_key_exists($request->query('estado'), ArchivoFoto::ESTADOS)) {
            $query->where('estado', $request->query('estado'));
        }
        match ($request->query('sin')) {
            'fecha' => $query->whereNull('anio'),
            'credito' => $query->whereNull('fotografo')->whereNull('credito'),
            'descripcion' => $query->whereNull('descripcion'),
            'personas' => $query->whereDoesntHave('personas'),
            default => null,
        };
        $ordenable = $request->filled('acontecimiento') || $request->filled('capitulo');
        $ordenable ? $query->orderBy('orden')->orderBy('id') : $query->latest('id');
        $pagina = $query->paginate(40);

        return response()->json([
            'data' => collect($pagina->items())->map(fn (ArchivoFoto $f) => PresentadorArchivo::foto($f, false, true) + [
                'estado' => $f->estado, 'estado_etiqueta' => $f->etiquetaEstado(), 'es_aporte' => $f->enviada_at !== null,
            ])->values(),
            'meta' => ['current_page' => $pagina->currentPage(), 'last_page' => $pagina->lastPage(), 'total' => $pagina->total(), 'ordenable' => $ordenable],
        ]);
    }

    public function foto(Request $request, ArchivoFoto $foto): JsonResponse
    {
        $this->authorize('view', $foto);

        return response()->json(['data' => $this->ficha($foto, $request)]);
    }

    public function subir(Request $request): JsonResponse
    {
        $this->authorize('create', ArchivoFoto::class);
        $datos = $request->validate($this->archivo->reglasArchivo() + $this->archivo->reglasMetadatos(true) + ['confirmar_duplicado' => 'nullable|boolean'], $this->archivo->mensajes());
        if (! empty($datos['sede_id'])) {
            $this->authorize('asignarSede', [ArchivoFoto::class, (int) $datos['sede_id']]);
        }
        if (! $request->boolean('confirmar_duplicado')) {
            $iguales = $this->archivo->duplicados(ImagenesArchivo::hashDe($request->file('archivo')));
            if ($iguales->isNotEmpty()) {
                return response()->json([
                    'message' => 'Esta fotografía podría ya formar parte del archivo.',
                    'duplicados' => $iguales->map(fn ($f) => ['id' => $f->id, 'titulo' => $f->tituloVisible()])->values(),
                ], 409);
            }
        }
        $foto = $this->archivo->subir($request->file('archivo'), collect($datos)->except(['archivo', 'confirmar_duplicado'])->all(), $request->user(), true);

        return response()->json(['data' => $this->ficha($foto, $request)], 201);
    }

    public function actualizar(Request $request, ArchivoFoto $foto): JsonResponse
    {
        $this->authorize('editarComoEquipo', $foto);
        $datos = $request->validate($this->archivo->reglasMetadatos(true) + ['orden' => 'nullable|integer|min:0', 'mostrar_aportante' => 'nullable|boolean'], $this->archivo->mensajes());
        if (! empty($datos['sede_id'])) {
            $this->authorize('asignarSede', [ArchivoFoto::class, (int) $datos['sede_id']]);
        }
        $foto = $this->archivo->actualizar($foto, collect($datos)->except('orden')->all(), $request->user(), true);
        if ($request->filled('orden')) {
            $foto->forceFill(['orden' => (int) $request->input('orden')])->save();
        }

        return response()->json(['data' => $this->ficha($foto, $request)]);
    }

    public function reemplazar(Request $request, ArchivoFoto $foto): JsonResponse
    {
        $this->authorize('editarComoEquipo', $foto);
        $request->validate($this->archivo->reglasArchivo(), $this->archivo->mensajes());
        $foto = $this->archivo->reemplazarImagen($foto, $request->file('archivo'), $request->user());

        return response()->json(['data' => $this->ficha($foto, $request)]);
    }

    public function estado(Request $request, ArchivoFoto $foto): JsonResponse
    {
        $datos = $request->validate([
            'accion' => ['required', Rule::in(['aprobar', 'rechazar', 'cambios', 'publicar', 'ocultar'])],
            'notas' => [Rule::requiredIf(in_array($request->input('accion'), ['rechazar', 'cambios'], true)), 'nullable', 'string', 'max:3000'],
        ], ['notas.required' => 'Escribí el motivo o lo que necesitás que complete.']);
        $this->authorize(ArchivoService::permisoDeAccion($datos['accion']), $foto);
        $foto = $this->archivo->cambiarEstado($foto, $datos['accion'], $datos['notas'] ?? null, $request->user());

        return response()->json(['data' => $this->ficha($foto, $request)]);
    }

    public function eliminar(Request $request, ArchivoFoto $foto): JsonResponse
    {
        $this->authorize('delete', $foto);
        $this->archivo->eliminar($foto);

        return response()->json(['ok' => true]);
    }

    public function lote(Request $request): JsonResponse
    {
        $datos = $request->validate($this->archivo->reglasMetadatos(true) + [
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'integer',
            'accion' => ['required', Rule::in(['aplicar', 'publicar', 'ocultar', 'eliminar'])],
            'reemplazar_tags' => 'nullable|boolean',
            'publicar' => 'nullable|boolean',
        ], $this->archivo->mensajes());
        if (! empty($datos['sede_id'])) {
            $this->authorize('asignarSede', [ArchivoFoto::class, (int) $datos['sede_id']]);
        }
        $fotos = ArchivoFoto::query()->whereIn('id', $datos['ids'])->get();

        return response()->json($this->archivo->accionEnLote($fotos, $datos['accion'], $datos, $request->user()));
    }

    public function ordenar(Request $request): JsonResponse
    {
        $datos = $request->validate(['ids' => 'required|array|min:1|max:500', 'ids.*' => 'integer']);
        $fotos = ArchivoFoto::query()->whereIn('id', $datos['ids'])->get();
        foreach ($fotos as $f) {
            $this->authorize('editarComoEquipo', $f);
        }
        $this->archivo->ordenar(array_values(array_intersect($datos['ids'], $fotos->pluck('id')->all())));

        return response()->json(['ok' => true]);
    }

    public function moderacion(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ArchivoFoto::class);
        $estado = in_array($request->query('estado'), ['pendiente', 'cambios', 'rechazada'], true) ? $request->query('estado') : 'pendiente';
        $fotos = $this->consultas->gestionables($request->user());
        $pagina = (clone $fotos)->where('estado', $estado)->whereNotNull('aportada_por')
            ->with(['aportante:id,name', 'personas.persona', 'acontecimiento', 'sede', 'tags', 'capitulo'])
            ->orderBy('enviada_at')->orderBy('id')->paginate(20);

        return response()->json([
            'data' => collect($pagina->items())->map(fn (ArchivoFoto $f) => PresentadorArchivo::fotoGestion($f, $request->user())
                + ['duplicados' => $f->hash ? $this->archivo->duplicados($f->hash, $f->id)->count() : 0])->values(),
            'meta' => ['current_page' => $pagina->currentPage(), 'last_page' => $pagina->lastPage(), 'total' => $pagina->total(),
                'conteos' => $this->consultas->conteosModeracion($fotos)],
        ]);
    }

    public function personas(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ArchivoFoto::class);
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 60);
        if (mb_strlen($q) < 2) {
            return response()->json(['data' => []]);
        }
        $delSistema = Persona::query()->buscar($q)->orderBy('nombre')->limit(12)->get(['id', 'nombre', 'apellido'])
            ->map(fn (Persona $p) => ['persona_id' => $p->id, 'nombre' => $p->nombre_completo]);
        $libres = ArchivoFotoPersona::query()->whereNull('persona_id')->where('nombre', 'like', '%'.$q.'%')
            ->distinct()->limit(8)->pluck('nombre')->map(fn ($n) => ['persona_id' => null, 'nombre' => $n]);

        return response()->json(['data' => $delSistema->concat($libres)->unique('nombre')->values()]);
    }

    // ── Capítulos y acontecimientos ──────────────────────────────────────────

    public function capitulos(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ArchivoCapitulo::class);

        return response()->json(['data' => ArchivoCapitulo::query()->with('portada')->withCount(['acontecimientos', 'fotos'])
            ->orderBy('orden')->orderBy('anio_desde')->get()
            ->map(fn ($c) => PresentadorArchivo::capitulo($c) + ['publicado' => $c->publicado, 'orden' => $c->orden, 'acontecimientos_count' => $c->acontecimientos_count, 'fotos_count' => $c->fotos_count])]);
    }

    public function guardarCapitulo(Request $request, ?ArchivoCapitulo $capitulo = null): JsonResponse
    {
        $capitulo ? $this->authorize('update', $capitulo) : $this->authorize('create', ArchivoCapitulo::class);
        $datos = $request->validate($this->editorial->reglasCapitulo());
        $datos['publicado'] = $request->boolean('publicado');
        $c = $this->editorial->guardarCapitulo($capitulo, $datos);

        return response()->json(['data' => PresentadorArchivo::capitulo($c->load('portada')) + ['publicado' => $c->publicado]], $capitulo ? 200 : 201);
    }

    public function eliminarCapitulo(ArchivoCapitulo $capitulo): JsonResponse
    {
        $this->authorize('delete', $capitulo);
        $this->editorial->eliminarCapitulo($capitulo);

        return response()->json(['ok' => true]);
    }

    public function acontecimientos(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ArchivoAcontecimiento::class);
        $pagina = ArchivoAcontecimiento::query()->with(['portada', 'capitulo:id,titulo'])->withCount('fotos')
            ->when(trim((string) $request->query('q')), fn ($w, $q) => $w->where('titulo', 'like', '%'.$q.'%'))
            ->when($request->integer('capitulo'), fn ($w, $c) => $w->where('capitulo_id', $c))
            ->cronologico()->paginate(50);

        return response()->json([
            'data' => collect($pagina->items())->map(fn ($a) => PresentadorArchivo::acontecimiento($a) + [
                'publicado' => $a->publicado, 'capitulo_id' => $a->capitulo_id, 'capitulo' => $a->capitulo?->titulo, 'fotos_count' => $a->fotos_count,
            ])->values(),
            'meta' => ['current_page' => $pagina->currentPage(), 'last_page' => $pagina->lastPage(), 'total' => $pagina->total()],
        ]);
    }

    public function acontecimiento(ArchivoAcontecimiento $acontecimiento): JsonResponse
    {
        $this->authorize('update', $acontecimiento);
        $a = $acontecimiento->load(['portada', 'capitulo', 'sede', 'relacionados', 'fotos']);

        return response()->json(['data' => PresentadorArchivo::acontecimiento($a) + [
            'relato' => $a->relato, 'precision' => $a->precision, 'fecha_iso' => $a->fecha?->toDateString(),
            'lugar_detalle' => ['lugar' => $a->lugar, 'ciudad' => $a->ciudad, 'pais' => $a->pais, 'latitud' => $a->latitud, 'longitud' => $a->longitud],
            'capitulo_id' => $a->capitulo_id, 'sede_id' => $a->sede_id, 'evento_id' => $a->evento_id, 'show_id' => $a->show_id,
            'portada_foto_id' => $a->portada_foto_id, 'publicado' => $a->publicado,
            'relacionados_ids' => $a->relacionados->pluck('id'),
            'fotos' => $a->fotos->map(fn ($f) => PresentadorArchivo::foto($f, false, true) + ['estado' => $f->estado])->values(),
        ]]);
    }

    public function guardarAcontecimiento(Request $request, ?ArchivoAcontecimiento $acontecimiento = null): JsonResponse
    {
        $acontecimiento ? $this->authorize('update', $acontecimiento) : $this->authorize('create', ArchivoAcontecimiento::class);
        $datos = $request->validate($this->editorial->reglasAcontecimiento());
        $this->authorize('createEnSede', [ArchivoAcontecimiento::class, $datos['sede_id'] ?? null]);
        $datos['publicado'] = $request->boolean('publicado');
        $a = $this->editorial->guardarAcontecimiento($acontecimiento, $datos);

        return $this->acontecimiento($a)->setStatusCode($acontecimiento ? 200 : 201);
    }

    public function eliminarAcontecimiento(ArchivoAcontecimiento $acontecimiento): JsonResponse
    {
        $this->authorize('delete', $acontecimiento);
        $this->editorial->eliminarAcontecimiento($acontecimiento);

        return response()->json(['ok' => true]);
    }

    /** @return array<string, mixed> */
    private function ficha(ArchivoFoto $foto, Request $request): array
    {
        return PresentadorArchivo::fotoGestion($foto->fresh(['tags', 'personas.persona', 'revisiones.user', 'acontecimiento', 'capitulo', 'sede', 'aportante']), $request->user());
    }
}
