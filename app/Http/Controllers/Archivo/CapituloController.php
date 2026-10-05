<?php

namespace App\Http\Controllers\Archivo;

use App\Domain\Archivo\ArchivoEditorialService;
use App\Http\Controllers\Controller;
use App\Models\ArchivoCapitulo;
use App\Models\ArchivoFoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Capítulos del archivo (títulos, período, portada, orden y sus acontecimientos). */
class CapituloController extends Controller
{
    public function __construct(private readonly ArchivoEditorialService $editorial) {}

    public function index(): View
    {
        $this->authorize('viewAny', ArchivoCapitulo::class);

        return view('archivo.gestion.capitulos', [
            'capitulos' => ArchivoCapitulo::query()->with('portada')
                ->withCount(['acontecimientos', 'fotos'])
                ->orderBy('orden')->orderBy('anio_desde')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', ArchivoCapitulo::class);

        return view('archivo.gestion.capitulo', ['capitulo' => new ArchivoCapitulo, 'fotosCandidatas' => collect()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ArchivoCapitulo::class);
        $datos = $request->validate($this->editorial->reglasCapitulo());
        $datos['publicado'] = $request->boolean('publicado');
        $capitulo = $this->editorial->guardarCapitulo(null, $datos);

        return redirect()->route('archivo.gestion.capitulos.edit', $capitulo)->with('success', 'Creamos el capítulo. Ahora sumale acontecimientos.');
    }

    public function edit(ArchivoCapitulo $capitulo): View
    {
        $this->authorize('update', $capitulo);
        $capitulo->load(['portada', 'acontecimientos' => fn ($q) => $q->withCount('fotos')]);

        return view('archivo.gestion.capitulo', [
            'capitulo' => $capitulo,
            'fotosCandidatas' => ArchivoFoto::query()->where('capitulo_id', $capitulo->id)->whereNotNull('derivados')->orderBy('orden')->limit(60)->get(),
        ]);
    }

    public function update(Request $request, ArchivoCapitulo $capitulo): RedirectResponse
    {
        $this->authorize('update', $capitulo);
        $datos = $request->validate($this->editorial->reglasCapitulo());
        $datos['publicado'] = $request->boolean('publicado');
        $this->editorial->guardarCapitulo($capitulo, $datos);

        return redirect()->route('archivo.gestion.capitulos.edit', $capitulo)->with('success', 'Guardamos el capítulo.');
    }

    public function destroy(ArchivoCapitulo $capitulo): RedirectResponse
    {
        $this->authorize('delete', $capitulo);
        $this->editorial->eliminarCapitulo($capitulo);

        return redirect()->route('archivo.gestion.capitulos.index')->with('success', 'Eliminamos el capítulo. Sus acontecimientos y fotos quedaron sin capítulo.');
    }

    public function ordenar(Request $request): JsonResponse
    {
        $this->authorize('create', ArchivoCapitulo::class);
        $datos = $request->validate(['ids' => 'required|array|max:300', 'ids.*' => 'integer']);
        $this->editorial->ordenar(ArchivoCapitulo::class, $datos['ids']);

        return response()->json(['ok' => true]);
    }
}
