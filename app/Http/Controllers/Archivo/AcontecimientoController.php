<?php

namespace App\Http\Controllers\Archivo;

use App\Domain\Archivo\ArchivoEditorialService;
use App\Http\Controllers\Controller;
use App\Models\ArchivoAcontecimiento;
use App\Models\ArchivoCapitulo;
use App\Models\ArchivoFoto;
use App\Models\Evento;
use App\Models\Sede;
use App\Models\Show;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Acontecimientos: el relato fechado que agrupa fotos dentro de un capítulo. */
class AcontecimientoController extends Controller
{
    public function __construct(private readonly ArchivoEditorialService $editorial) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ArchivoAcontecimiento::class);
        $q = trim((string) $request->query('q', ''));

        return view('archivo.gestion.acontecimientos', [
            'acontecimientos' => ArchivoAcontecimiento::query()->with(['capitulo:id,titulo', 'portada', 'sede:id,nombre'])
                ->withCount('fotos')
                ->when($q !== '', fn ($w) => $w->where('titulo', 'like', '%'.$q.'%'))
                ->when($request->query('sin') === 'portada', fn ($w) => $w->whereNull('portada_foto_id'))
                ->when($request->integer('capitulo'), fn ($w, $c) => $w->where('capitulo_id', $c))
                ->cronologico()->paginate(50)->withQueryString(),
            'capitulos' => ArchivoCapitulo::query()->cronologico()->get(['id', 'titulo']),
            'q' => $q,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', ArchivoAcontecimiento::class);
        $a = new ArchivoAcontecimiento(['capitulo_id' => $request->integer('capitulo') ?: null, 'precision' => 'anio']);

        return view('archivo.gestion.acontecimiento', $this->opciones($a));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ArchivoAcontecimiento::class);
        $datos = $request->validate($this->editorial->reglasAcontecimiento());
        $this->authorize('createEnSede', [ArchivoAcontecimiento::class, $datos['sede_id'] ?? null]);
        $datos['publicado'] = $request->boolean('publicado');
        $a = $this->editorial->guardarAcontecimiento(null, $datos);

        return redirect()->route('archivo.gestion.eventos.edit', $a)->with('success', 'Creamos el acontecimiento. Ahora sumale fotos.');
    }

    public function edit(ArchivoAcontecimiento $acontecimiento): View
    {
        $this->authorize('update', $acontecimiento);
        $acontecimiento->load(['fotos', 'relacionados:id,titulo,anio', 'portada']);

        return view('archivo.gestion.acontecimiento', $this->opciones($acontecimiento));
    }

    public function update(Request $request, ArchivoAcontecimiento $acontecimiento): RedirectResponse
    {
        $this->authorize('update', $acontecimiento);
        $datos = $request->validate($this->editorial->reglasAcontecimiento());
        $this->authorize('createEnSede', [ArchivoAcontecimiento::class, $datos['sede_id'] ?? null]);
        $datos['publicado'] = $request->boolean('publicado');
        $datos['relacionados'] ??= [];
        $this->editorial->guardarAcontecimiento($acontecimiento, $datos);

        return redirect()->route('archivo.gestion.eventos.edit', $acontecimiento)->with('success', 'Guardamos el acontecimiento.');
    }

    public function destroy(ArchivoAcontecimiento $acontecimiento): RedirectResponse
    {
        $this->authorize('delete', $acontecimiento);
        $this->editorial->eliminarAcontecimiento($acontecimiento);

        return redirect()->route('archivo.gestion.eventos.index')->with('success', 'Eliminamos el acontecimiento. Sus fotos siguen en el archivo.');
    }

    public function ordenar(Request $request): JsonResponse
    {
        $datos = $request->validate(['ids' => 'required|array|max:300', 'ids.*' => 'integer']);
        foreach (ArchivoAcontecimiento::query()->whereIn('id', $datos['ids'])->get() as $a) {
            $this->authorize('update', $a);
        }
        $this->editorial->ordenar(ArchivoAcontecimiento::class, $datos['ids']);

        return response()->json(['ok' => true]);
    }

    /** @return array<string, mixed> */
    private function opciones(ArchivoAcontecimiento $a): array
    {
        return [
            'a' => $a,
            'capitulos' => ArchivoCapitulo::query()->cronologico()->get(['id', 'titulo', 'anio_desde']),
            'sedes' => Sede::query()->orderBy('nombre')->get(['id', 'nombre']),
            'eventos' => Evento::query()->orderByDesc('fecha')->limit(300)->get(['id', 'titulo', 'fecha']),
            'shows' => Show::query()->orderByDesc('fecha')->limit(300)->get(['id', 'titulo', 'fecha']),
            'otros' => ArchivoAcontecimiento::query()->whereKeyNot($a->id ?? 0)->cronologico()->get(['id', 'titulo', 'anio']),
            'fotosAcontecimiento' => $a->exists ? $a->fotos : collect(),
            'precisiones' => ArchivoFoto::PRECISIONES,
        ];
    }
}
