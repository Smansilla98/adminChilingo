<?php

namespace App\Http\Controllers;

use App\Domain\Acceso\EdicionPrograma;
use App\Domain\Partituras\VideosToque;
use App\Models\ProgramaRitmo;
use App\Support\ArchivoSeguro;
use App\Support\VideosMultipista;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** Edición de los videos multipista desde la ficha del toque (permiso partituras.edit o admin). */
class PartituraVideosController extends Controller
{
    public function store(Request $request, ProgramaRitmo $programaRitmo, VideosToque $videos): RedirectResponse
    {
        $this->exigirEdicion();
        $datos = $request->validate([
            'archivo' => ['required', 'file', 'extensions:'.implode(',', VideosMultipista::EXTENSIONES), 'max:'.VideosMultipista::MAX_KB, ArchivoSeguro::regla()],
            'instrumento' => 'required|string|max:40',
            'titulo' => 'nullable|string|max:80',
            'offset_seg' => 'nullable|numeric|min:-10|max:10',
        ], [
            'archivo.required' => 'Elegí un video (MP4, WebM o MOV).',
            'archivo.extensions' => 'El video tiene que ser MP4, WebM o MOV.',
            'archivo.max' => 'El video no puede pasar de 100 MB.',
        ]);
        try {
            $videos->agregar($programaRitmo, $request->file('archivo'), $this->instrumentoValido($datos['instrumento']), $datos['titulo'] ?? null, (float) ($datos['offset_seg'] ?? 0));
        } catch (RuntimeException $e) {
            return back()->withErrors(['archivo' => $e->getMessage()]);
        }

        return back()->with('status', 'Video agregado.');
    }

    public function update(Request $request, ProgramaRitmo $programaRitmo, string $id, VideosToque $videos): RedirectResponse
    {
        $this->exigirEdicion();
        $datos = $request->validate([
            'titulo' => 'nullable|string|max:80',
            'instrumento' => 'nullable|string|max:40',
            'offset_seg' => 'nullable|numeric|min:-10|max:10',
        ]);
        $cambios = [];
        if ($request->has('titulo')) {
            $cambios['titulo'] = (string) ($datos['titulo'] ?? '');
        }
        if ($request->filled('instrumento')) {
            $cambios['instrumento'] = $this->instrumentoValido((string) $datos['instrumento']);
        }
        if ($request->has('offset_seg')) {
            $cambios['offset_seg'] = (float) ($datos['offset_seg'] ?? 0);
        }
        abort_unless($videos->actualizar($programaRitmo, $id, $cambios) !== null, 404, 'No está ese video.');

        return back()->with('status', 'Video actualizado.');
    }

    public function mover(Request $request, ProgramaRitmo $programaRitmo, string $id, VideosToque $videos): RedirectResponse
    {
        $this->exigirEdicion();
        $direccion = $request->input('direccion') === 'arriba' ? -1 : 1;
        $videos->mover($programaRitmo, $id, $direccion);

        return back();
    }

    public function destroy(ProgramaRitmo $programaRitmo, string $id, VideosToque $videos): RedirectResponse
    {
        $this->exigirEdicion();
        abort_unless($videos->quitar($programaRitmo, $id), 404, 'No está ese video.');

        return back()->with('status', 'Video quitado.');
    }

    private function instrumentoValido(string $id): string
    {
        return $id === VideosMultipista::INSTRUMENTO_VOZ || (array_key_exists($id, \App\Support\PartituraScore::INSTRUMENTOS) && $id !== 'todos')
            ? $id
            : VideosMultipista::INSTRUMENTO_VOZ;
    }

    private function exigirEdicion(): void
    {
        abort_unless(EdicionPrograma::puedeEditar(auth()->user()), 403, 'Para editar los videos hace falta el permiso de edición de partituras.');
    }
}
