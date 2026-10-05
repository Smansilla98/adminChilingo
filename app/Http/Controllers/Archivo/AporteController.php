<?php

namespace App\Http\Controllers\Archivo;

use App\Domain\Archivo\ArchivoService;
use App\Domain\Archivo\ImagenesArchivo;
use App\Domain\Archivo\PresentadorArchivo;
use App\Http\Controllers\Controller;
use App\Models\ArchivoAcontecimiento;
use App\Models\ArchivoFoto;
use App\Models\Sede;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aportes de la comunidad: cualquier cuenta activa sube fotos, completa lo que
 * recuerda y las envía a revisión. Solo ve y corrige lo propio.
 */
class AporteController extends Controller
{
    public function __construct(private readonly ArchivoService $archivo) {}

    public function create(Request $request): View
    {
        $this->authorize('aportar', ArchivoFoto::class);

        return view('archivo.aportes.create', $this->opciones() + [
            'seo' => ['titulo' => 'Compartí un recuerdo — Archivo de La Chilinga', 'descripcion' => 'Sumá tus fotos al archivo histórico de La Chilinga.', 'noindex' => true],
        ]);
    }

    /** Sube una imagen (el cargador manda de a una). Avisa si ya existe en el archivo. */
    public function subir(Request $request): JsonResponse
    {
        $this->authorize('aportar', ArchivoFoto::class);
        $request->validate($this->archivo->reglasArchivo() + ['confirmar_duplicado' => 'nullable|boolean'], $this->archivo->mensajes());

        if ($respuesta = $this->siEsDuplicado($request)) {
            return $respuesta;
        }
        $foto = $this->archivo->subir($request->file('archivo'), [], $request->user(), false);

        return response()->json(['data' => $this->resumen($foto)], 201);
    }

    /**
     * Aplica los datos comunes a las fotos recién subidas y (opcional) las envía a
     * revisión. Sin JS, acepta las imágenes en el mismo formulario.
     */
    public function lote(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $this->authorize('aportar', ArchivoFoto::class);
        $datos = $request->validate($this->archivo->reglasMetadatos(false) + [
            'ids' => 'nullable|array|max:200',
            'ids.*' => 'integer',
            'archivos' => 'nullable|array|max:20',
            'archivos.*' => $this->archivo->reglasArchivo()['archivo'],
            'enviar' => 'nullable|boolean',
        ], $this->archivo->mensajes());

        $ids = collect($datos['ids'] ?? []);
        foreach ($request->file('archivos', []) as $archivo) {
            $ids->push($this->archivo->subir($archivo, [], $user, false)->id);
        }
        $fotos = ArchivoFoto::query()->whereIn('id', $ids)->where('aportada_por', $user->id)
            ->whereIn('estado', ArchivoFoto::EDITABLES_POR_APORTANTE)->get();
        if ($fotos->isEmpty()) {
            throw ValidationException::withMessages(['archivo' => 'Subí al menos una foto.']);
        }

        $metadatos = collect($datos)->except(['ids', 'archivos', 'enviar'])->all();
        if (array_key_exists('mostrar_aportante', $metadatos)) {
            $metadatos['mostrar_aportante'] = $request->boolean('mostrar_aportante');
        }
        $this->archivo->aplicarEnLote($fotos, $metadatos, $user, false);

        $enviadas = 0;
        $pendientes = [];
        if ($request->boolean('enviar')) {
            foreach ($fotos as $foto) {
                try {
                    $this->archivo->enviar($foto->fresh(), $user);
                    $enviadas++;
                } catch (ValidationException) {
                    $pendientes[] = $foto->id;
                }
            }
        }

        $mensaje = $enviadas
            ? ($enviadas === 1 ? '¡Gracias! Tu foto quedó en revisión.' : "¡Gracias! Tus {$enviadas} fotos quedaron en revisión.")
            : 'Guardamos tus fotos como borrador. Podés completarlas y enviarlas cuando quieras.';
        if ($pendientes) {
            $mensaje .= ' A '.count($pendientes).' les falta el año: completalo para enviarlas.';
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'enviadas' => $enviadas, 'sin_enviar' => $pendientes, 'mensaje' => $mensaje]);
        }

        return redirect()->route('archivo.aportes.index')->with('success', $mensaje);
    }

    public function index(Request $request): View
    {
        $estado = $request->query('estado');
        $base = ArchivoFoto::query()->where('aportada_por', $request->user()->id);
        $fotos = (clone $base)
            ->when($estado && array_key_exists($estado, ArchivoFoto::ESTADOS), fn ($q) => $q->where('estado', $estado))
            ->latest('id')->paginate(24)->withQueryString();

        return view('archivo.aportes.index', [
            'fotos' => $fotos,
            'estado' => $estado,
            'conteos' => (clone $base)->selectRaw('estado, count(*) as n')->groupBy('estado')->pluck('n', 'estado'),
            'seo' => ['titulo' => 'Mis aportes — Archivo de La Chilinga', 'noindex' => true],
        ]);
    }

    public function show(Request $request, ArchivoFoto $foto): View
    {
        abort_unless((int) $foto->aportada_por === (int) $request->user()->id, 404);
        $foto->load(['tags', 'personas.persona', 'revisiones.user', 'sede', 'acontecimiento']);

        return view('archivo.aportes.show', $this->opciones() + [
            'foto' => $foto,
            'editable' => $request->user()->can('update', $foto),
            'visor' => [PresentadorArchivo::foto($foto)],
            'seo' => ['titulo' => $foto->tituloVisible().' — Mis aportes', 'noindex' => true],
        ]);
    }

    public function update(Request $request, ArchivoFoto $foto): RedirectResponse
    {
        abort_unless((int) $foto->aportada_por === (int) $request->user()->id, 404);
        $this->authorize('update', $foto);
        $datos = $request->validate($this->archivo->reglasMetadatos(false), $this->archivo->mensajes());
        $datos['mostrar_aportante'] = $request->boolean('mostrar_aportante');
        $datos['tags'] ??= [];
        $datos['personas'] ??= [];
        $this->archivo->actualizar($foto, $datos, $request->user(), false);

        if ($request->boolean('enviar') && $request->user()->can('enviar', $foto->fresh())) {
            $this->archivo->enviar($foto->fresh(), $request->user());

            return redirect()->route('archivo.aportes.show', $foto)->with('success', 'Enviamos tu foto a revisión. ¡Gracias!');
        }

        return redirect()->route('archivo.aportes.show', $foto)->with('success', 'Guardamos los cambios.');
    }

    public function enviar(Request $request, ArchivoFoto $foto): RedirectResponse
    {
        $this->authorize('enviar', $foto);
        $this->archivo->enviar($foto, $request->user());

        return redirect()->route('archivo.aportes.show', $foto)->with('success', 'Enviamos tu foto a revisión. ¡Gracias!');
    }

    public function destroy(Request $request, ArchivoFoto $foto): RedirectResponse
    {
        abort_unless((int) $foto->aportada_por === (int) $request->user()->id, 404);
        $this->authorize('delete', $foto);
        $this->archivo->eliminar($foto);

        return redirect()->route('archivo.aportes.index')->with('success', 'Eliminamos la foto.');
    }

    public function original(Request $request, ArchivoFoto $foto, ImagenesArchivo $imagenes): Response
    {
        $this->authorize('descargarOriginal', $foto);

        return $imagenes->descargarOriginal($foto);
    }

    /** Si la imagen ya está en el archivo y no se confirmó, responde 409 con las coincidencias. */
    private function siEsDuplicado(Request $request): ?JsonResponse
    {
        if ($request->boolean('confirmar_duplicado')) {
            return null;
        }
        $iguales = $this->archivo->duplicados(ImagenesArchivo::hashDe($request->file('archivo')));
        if ($iguales->isEmpty()) {
            return null;
        }

        return response()->json([
            'message' => 'Esta fotografía podría ya formar parte del archivo.',
            'duplicados' => $iguales->map(fn (ArchivoFoto $f) => [
                'id' => $f->id,
                'titulo' => $f->esPublica() || $request->user()->can('view', $f) ? $f->tituloVisible() : 'Foto en revisión',
                'url' => $f->esPublica() ? $f->url() : null,
                'miniatura' => $request->user()->can('view', $f) ? $f->imagenUrl(400) : null,
            ])->values(),
        ], 409);
    }

    /** @return array<string, mixed> */
    private function resumen(ArchivoFoto $foto): array
    {
        return [
            'id' => $foto->id,
            'titulo' => $foto->titulo,
            'anio' => $foto->anio,
            'miniatura' => $foto->imagenUrl(400),
            'editar' => route('archivo.aportes.show', $foto),
        ];
    }

    /** @return array<string, mixed> */
    private function opciones(): array
    {
        return [
            'sedes' => Sede::query()->orderBy('nombre')->get(['id', 'nombre']),
            'acontecimientos' => ArchivoAcontecimiento::query()->publicados()->cronologico()->get(['id', 'titulo', 'anio']),
            'tipos' => ArchivoFoto::TIPOS,
            'fuentes' => ArchivoFoto::FUENTES,
            'precisiones' => ArchivoFoto::PRECISIONES,
        ];
    }
}
