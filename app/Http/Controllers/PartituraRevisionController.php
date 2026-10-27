<?php

namespace App\Http\Controllers;

use App\Domain\Partituras\RevisionPartituras;
use App\Models\ProgramaRitmo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pasajes de las partituras marcados «Revisar:» al transcribir el cuadernillo,
 * para que la escuela los confirme uno por uno.
 */
class PartituraRevisionController extends Controller
{
    public function index(RevisionPartituras $revision): View
    {
        $pendientes = $revision->pendientes();

        return view('programa.revision', [
            'pendientes' => $pendientes,
            'total' => $pendientes->sum(fn ($t) => count($t['marcas'])),
            'validadas' => $revision->validadas(),
        ]);
    }

    public function validar(Request $request, ProgramaRitmo $programaRitmo, RevisionPartituras $revision): RedirectResponse
    {
        $datos = $request->validate([
            'seccion' => 'required|string|max:120',
            'compas' => 'required|integer|min:1|max:999',
            'nota' => 'required|string|max:500',
        ]);

        $ok = $revision->validar($programaRitmo, $datos['seccion'], (int) $datos['compas'], $datos['nota'], $request->user());

        return redirect()->to(route('programa.revision').'#toque-'.$programaRitmo->id)->with(
            $ok ? 'success' : 'warning',
            $ok
                ? "Listo: {$programaRitmo->nombre}, {$datos['seccion']} compás {$datos['compas']} quedó validado."
                : 'Ese pasaje ya no está marcado (quizás alguien lo corrigió en el editor).',
        );
    }
}
