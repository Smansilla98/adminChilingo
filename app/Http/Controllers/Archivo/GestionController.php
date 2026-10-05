<?php

namespace App\Http\Controllers\Archivo;

use App\Domain\Archivo\ArchivoConsultas;
use App\Domain\Archivo\ArchivoService;
use App\Domain\Archivo\ImagenesArchivo;
use App\Http\Controllers\Controller;
use App\Models\ArchivoAcontecimiento;
use App\Models\ArchivoCapitulo;
use App\Models\ArchivoFoto;
use App\Models\ArchivoFotoPersona;
use App\Models\BibliotecaTag;
use App\Models\Persona;
use App\Models\Sede;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Backoffice del archivo: tablero, grilla con filtros y selección múltiple, carga
 * masiva, edición, orden y moderación. Cada acción la decide ArchivoFotoPolicy.
 */
class GestionController extends Controller
{
    public function __construct(
        private readonly ArchivoService $archivo,
        private readonly ArchivoConsultas $consultas,
    ) {}

    public function tablero(Request $request): View
    {
        $this->authorize('viewAny', ArchivoFoto::class);
        $fotos = $this->consultas->gestionables($request->user());

        $porDecada = (clone $fotos)->whereNotNull('anio')->pluck('anio')
            ->countBy(fn ($anio) => intdiv((int) $anio, 10) * 10)->sortKeys();

        return view('archivo.gestion.tablero', [
            'totales' => [
                'fotos' => (clone $fotos)->count(),
                'publicadas' => (clone $fotos)->publicadas()->count(),
                'capitulos' => ArchivoCapitulo::query()->count(),
                'acontecimientos' => ArchivoAcontecimiento::query()->count(),
                'personas' => ArchivoFotoPersona::query()->whereNotNull('persona_id')->distinct()->count('persona_id')
                    + ArchivoFotoPersona::query()->whereNull('persona_id')->distinct()->count('nombre'),
                'etiquetas' => BibliotecaTag::query()->whereHas('archivoFotos')->count(),
            ],
            'calidad' => [
                'sin_fecha' => (clone $fotos)->whereNull('anio')->whereNotIn('estado', ['rechazada'])->count(),
                'sin_credito' => (clone $fotos)->whereNull('fotografo')->whereNull('credito')->whereNotIn('estado', ['rechazada'])->count(),
                'sin_descripcion' => (clone $fotos)->whereNull('descripcion')->whereNotIn('estado', ['rechazada'])->count(),
                'sin_portada' => ArchivoAcontecimiento::query()->whereNull('portada_foto_id')->count(),
                'borradores' => (clone $fotos)->where('estado', 'borrador')->count(),
            ],
            'moderacion' => $this->consultas->conteosModeracion($fotos),
            'porDecada' => $porDecada,
            'recientes' => (clone $fotos)->latest('id')->limit(12)->get(),
        ]);
    }

    public function fotos(Request $request): View
    {
        $this->authorize('viewAny', ArchivoFoto::class);
        $f = $request->only(['q', 'estado', 'decada', 'anio', 'sin', 'capitulo', 'acontecimiento', 'sede', 'tag', 'tipo']);
        $query = $this->consultas->gestionables($request->user())->with(['acontecimiento:id,titulo', 'sede:id,nombre', 'aportante:id,name']);
        $this->consultas->filtrar($query, ['q' => $f['q'] ?? null, 'decada' => $f['decada'] ?? null, 'anio' => $f['anio'] ?? null,
            'capitulo' => $f['capitulo'] ?? null, 'acontecimiento' => $f['acontecimiento'] ?? null, 'sede' => $f['sede'] ?? null,
            'tipo' => $f['tipo'] ?? null, 'tags' => array_filter([$f['tag'] ?? null])]);
        if (! empty($f['estado']) && array_key_exists($f['estado'], ArchivoFoto::ESTADOS)) {
            $query->where('estado', $f['estado']);
        }
        match ($f['sin'] ?? null) {
            'fecha' => $query->whereNull('anio'),
            'credito' => $query->whereNull('fotografo')->whereNull('credito'),
            'descripcion' => $query->whereNull('descripcion'),
            'personas' => $query->whereDoesntHave('personas'),
            default => null,
        };
        // Dentro de un acontecimiento o capítulo se ve (y se arrastra) en su orden.
        $ordenable = ! empty($f['acontecimiento']) || ! empty($f['capitulo']);
        $ordenable ? $query->orderBy('orden')->orderBy('id') : $query->latest('id');

        return view('archivo.gestion.fotos', [
            'fotos' => $query->paginate($ordenable ? 200 : 48)->withQueryString(),
            'f' => $f,
            'ordenable' => $ordenable,
        ] + $this->opciones());
    }

    public function subirForm(Request $request): View
    {
        $this->authorize('create', ArchivoFoto::class);

        return view('archivo.gestion.subir', $this->opciones() + [
            'preAcontecimiento' => $request->integer('acontecimiento') ?: null,
            'preCapitulo' => $request->integer('capitulo') ?: null,
        ]);
    }

    public function subir(Request $request): JsonResponse
    {
        $this->authorize('create', ArchivoFoto::class);
        $request->validate($this->archivo->reglasArchivo() + ['confirmar_duplicado' => 'nullable|boolean'], $this->archivo->mensajes());

        if (! $request->boolean('confirmar_duplicado')) {
            $iguales = $this->archivo->duplicados(ImagenesArchivo::hashDe($request->file('archivo')));
            if ($iguales->isNotEmpty()) {
                return response()->json([
                    'message' => 'Esta fotografía podría ya formar parte del archivo.',
                    'duplicados' => $iguales->map(fn (ArchivoFoto $x) => ['id' => $x->id, 'titulo' => $x->tituloVisible(), 'url' => route('archivo.gestion.fotos.edit', $x)])->values(),
                ], 409);
            }
        }
        $foto = $this->archivo->subir($request->file('archivo'), [], $request->user(), true);

        return response()->json(['data' => [
            'id' => $foto->id,
            'titulo' => $foto->titulo,
            'miniatura' => $foto->imagenUrl(400),
            'editar' => route('archivo.gestion.fotos.edit', $foto),
        ]], 201);
    }

    /** Acciones sobre la selección: aplicar datos, publicar, ocultar o eliminar. */
    public function lote(Request $request): RedirectResponse
    {
        $user = $request->user();
        $datos = $request->validate($this->archivo->reglasMetadatos(true) + [
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'integer',
            'accion' => ['required', Rule::in(['aplicar', 'publicar', 'ocultar', 'eliminar'])],
            'reemplazar_tags' => 'nullable|boolean',
            'publicar' => 'nullable|boolean',
        ], $this->archivo->mensajes() + ['ids.required' => 'Elegí al menos una foto.']);

        if (! empty($datos['sede_id'])) {
            $this->authorize('asignarSede', [ArchivoFoto::class, (int) $datos['sede_id']]);
        }
        $fotos = ArchivoFoto::query()->whereIn('id', $datos['ids'])->get();
        ['hechas' => $hechas, 'omitidas' => $omitidas] = $this->archivo->accionEnLote($fotos, $datos['accion'], $datos + ['publicar' => $request->boolean('publicar')], $user);

        $mensaje = match ($datos['accion']) {
            'aplicar' => "Actualizamos {$hechas} fotos.",
            'publicar' => "Publicamos {$hechas} fotos.",
            'ocultar' => "Ocultamos {$hechas} fotos.",
            'eliminar' => "Eliminamos {$hechas} fotos.",
        };
        if ($omitidas) {
            $mensaje .= " {$omitidas} quedaron sin cambios: no tenés permiso sobre ellas.";
        }
        $volver = $request->input('volver');

        return ($volver && str_starts_with($volver, url('/archivo/gestion')) ? redirect($volver) : back())->with('success', $mensaje);
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

    public function editar(Request $request, ArchivoFoto $foto): View
    {
        $this->authorize('view', $foto);
        $foto->load(['tags', 'personas.persona', 'revisiones.user', 'aportante', 'revisor', 'acontecimiento', 'capitulo', 'sede']);

        return view('archivo.gestion.foto', $this->opciones() + [
            'foto' => $foto,
            'duplicados' => $foto->hash ? $this->archivo->duplicados($foto->hash, $foto->id) : collect(),
            'puede' => [
                'editar' => $request->user()->can('editarComoEquipo', $foto),
                'moderar' => $request->user()->can('moderate', $foto),
                'publicar' => $request->user()->can('publish', $foto),
                'eliminar' => $request->user()->can('delete', $foto),
            ],
        ]);
    }

    public function actualizar(Request $request, ArchivoFoto $foto): RedirectResponse
    {
        $this->authorize('editarComoEquipo', $foto);
        $datos = $request->validate($this->archivo->reglasMetadatos(true) + ['orden' => 'nullable|integer|min:0'], $this->archivo->mensajes());
        if (! empty($datos['sede_id'])) {
            $this->authorize('asignarSede', [ArchivoFoto::class, (int) $datos['sede_id']]);
        }
        foreach (['mostrar_aportante', 'destacada'] as $b) {
            $datos[$b] = $request->boolean($b);
        }
        $datos['tags'] ??= [];
        $datos['personas'] ??= [];
        $this->archivo->actualizar($foto, $datos, $request->user(), true);
        if ($request->filled('orden')) {
            $foto->forceFill(['orden' => (int) $request->input('orden')])->save();
        }

        return redirect()->route('archivo.gestion.fotos.edit', $foto)->with('success', 'Guardamos los datos de la foto.');
    }

    public function reemplazar(Request $request, ArchivoFoto $foto): RedirectResponse
    {
        $this->authorize('editarComoEquipo', $foto);
        $request->validate($this->archivo->reglasArchivo(), $this->archivo->mensajes());
        $this->archivo->reemplazarImagen($foto, $request->file('archivo'), $request->user());

        return redirect()->route('archivo.gestion.fotos.edit', $foto)->with('success', 'Reemplazamos la imagen. El original anterior se eliminó.');
    }

    public function estado(Request $request, ArchivoFoto $foto): RedirectResponse
    {
        $datos = $request->validate([
            'accion' => ['required', Rule::in(['aprobar', 'rechazar', 'cambios', 'publicar', 'ocultar'])],
            'notas' => [Rule::requiredIf(in_array($request->input('accion'), ['rechazar', 'cambios'], true)), 'nullable', 'string', 'max:3000'],
        ], ['notas.required' => 'Escribí el motivo o lo que necesitás que complete.']);
        $user = $request->user();

        $this->authorize(ArchivoService::permisoDeAccion($datos['accion']), $foto);
        $this->archivo->cambiarEstado($foto, $datos['accion'], $datos['notas'] ?? null, $user);

        $mensaje = [
            'aprobar' => 'Aprobada y publicada. Le avisamos a quien la aportó.',
            'rechazar' => 'Rechazada. Le avisamos a quien la aportó con el motivo.',
            'cambios' => 'Le pedimos los cambios a quien la aportó.',
            'publicar' => 'Publicada.',
            'ocultar' => 'Oculta: ya no se ve en el archivo público.',
        ][$datos['accion']];
        $volver = $request->input('volver');

        return ($volver && str_starts_with($volver, url('/archivo/gestion')) ? redirect($volver) : back())->with('success', $mensaje);
    }

    public function eliminar(Request $request, ArchivoFoto $foto): RedirectResponse
    {
        $this->authorize('delete', $foto);
        $this->archivo->eliminar($foto);

        return redirect()->route('archivo.gestion.fotos')->with('success', 'Eliminamos la foto y sus archivos.');
    }

    public function moderacion(Request $request): View
    {
        $this->authorize('viewAny', ArchivoFoto::class);
        $estado = in_array($request->query('estado'), ['pendiente', 'cambios', 'rechazada'], true) ? $request->query('estado') : 'pendiente';
        $fotos = $this->consultas->gestionables($request->user());

        $cola = (clone $fotos)->where('estado', $estado)
            ->whereNotNull('aportada_por')
            ->with(['aportante:id,name', 'personas.persona', 'acontecimiento:id,titulo', 'sede:id,nombre', 'tags'])
            ->orderBy('enviada_at')->orderBy('id')
            ->paginate(20)->withQueryString();

        // Coincidencias por hash, en una sola consulta para toda la página.
        $hashes = collect($cola->items())->pluck('hash')->filter()->unique();
        $repetidos = $hashes->isEmpty() ? collect() : ArchivoFoto::query()->whereIn('hash', $hashes)
            ->select('hash', DB::raw('count(*) as n'))->groupBy('hash')->having('n', '>', 1)->pluck('n', 'hash');

        return view('archivo.gestion.moderacion', [
            'cola' => $cola,
            'estado' => $estado,
            'conteos' => $this->consultas->conteosModeracion($fotos),
            'repetidos' => $repetidos,
        ]);
    }

    /** Personas del sistema y nombres ya usados en el archivo (para etiquetar fotos). */
    public function buscarPersonas(Request $request): JsonResponse
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

    /** @return array<string, mixed> */
    private function opciones(): array
    {
        return [
            'sedes' => Sede::query()->orderBy('nombre')->get(['id', 'nombre']),
            'capitulos' => ArchivoCapitulo::query()->cronologico()->get(['id', 'titulo', 'anio_desde']),
            'acontecimientos' => ArchivoAcontecimiento::query()->cronologico()->get(['id', 'titulo', 'anio', 'capitulo_id']),
            'tipos' => ArchivoFoto::TIPOS,
            'fuentes' => ArchivoFoto::FUENTES,
            'precisiones' => ArchivoFoto::PRECISIONES,
            'estados' => ArchivoFoto::ESTADOS,
        ];
    }
}
