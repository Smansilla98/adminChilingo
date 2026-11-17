<?php

namespace App\Http\Controllers;

use App\Models\ProgramaRitmo;
use App\Support\FichaToque;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Ficha del toque desde la web (la redacta la escuela; no cambia la partitura). */
class PartituraFichaController extends Controller
{
    public function guardar(Request $request, ProgramaRitmo $programaRitmo): RedirectResponse
    {
        abort_unless(auth()->user()?->can('partituras.admin'), 403);
        $data = $request->validate([
            'origen' => 'nullable|string|max:120',
            'compas' => 'nullable|string|max:8',
            'tempo_sugerido' => 'nullable|integer|min:30|max:260',
            'instrumentos' => 'nullable|array|max:9',
            'instrumentos.*' => 'string|max:40',
            'nota' => 'nullable|string|max:500',
        ]);
        FichaToque::guardar($programaRitmo, $data);

        return back()->with('status', 'Ficha del toque guardada.');
    }
}
