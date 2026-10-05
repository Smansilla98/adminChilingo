<?php

namespace App\Http\Controllers\Archivo;

use App\Http\Controllers\Controller;
use App\Models\ArchivoFoto;
use App\Models\BibliotecaTag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Etiquetas del archivo. Son las mismas de la biblioteca (`biblioteca_tags`): renombrar
 * o fusionar se refleja en ambas. Quitar una etiqueta del archivo no la borra de la biblioteca.
 */
class EtiquetaController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ArchivoFoto::class);

        return view('archivo.gestion.etiquetas', [
            'tags' => BibliotecaTag::query()->whereHas('archivoFotos')
                ->withCount(['archivoFotos', 'items as biblioteca_count'])
                ->orderByDesc('archivo_fotos_count')->orderBy('nombre')->get(),
            'puedeEditar' => $request->user()->acceso()->puedeGlobal('archivo.manage'),
        ]);
    }

    /** Renombrar; si el nombre nuevo ya existe, fusiona las dos etiquetas. */
    public function update(Request $request, BibliotecaTag $tag): RedirectResponse
    {
        abort_unless($request->user()->acceso()->puedeGlobal('archivo.manage'), 403);
        $datos = $request->validate(['nombre' => 'required|string|max:40']);
        $nombre = BibliotecaTag::normalizarNombre($datos['nombre']);
        $slug = BibliotecaTag::slugFromNombre($nombre);
        $existente = BibliotecaTag::query()->where('slug', $slug)->whereKeyNot($tag->id)->first();

        DB::transaction(function () use ($tag, $existente, $nombre, $slug) {
            if (! $existente) {
                $tag->update(['nombre' => $nombre, 'slug' => $slug]);

                return;
            }
            foreach (['archivo_foto_tag' => 'archivo_foto_id', 'biblioteca_item_tag' => 'biblioteca_item_id'] as $tabla => $col) {
                $ya = DB::table($tabla)->where('biblioteca_tag_id', $existente->id)->pluck($col);
                DB::table($tabla)->where('biblioteca_tag_id', $tag->id)->whereIn($col, $ya)->delete();
                DB::table($tabla)->where('biblioteca_tag_id', $tag->id)->update(['biblioteca_tag_id' => $existente->id]);
            }
            $existente->increment('usos', (int) $tag->usos);
            $tag->delete();
        });

        return back()->with('success', $existente ? "Fusionamos la etiqueta con #{$nombre}." : "Renombramos la etiqueta a #{$nombre}.");
    }

    /** Quita la etiqueta de todas las fotos del archivo (la biblioteca no cambia). */
    public function destroy(Request $request, BibliotecaTag $tag): RedirectResponse
    {
        abort_unless($request->user()->acceso()->puedeGlobal('archivo.manage'), 403);
        DB::table('archivo_foto_tag')->where('biblioteca_tag_id', $tag->id)->delete();
        if (! $tag->items()->exists()) {
            $tag->delete();
        }

        return back()->with('success', "Quitamos #{$tag->nombre} de las fotos del archivo.");
    }
}
