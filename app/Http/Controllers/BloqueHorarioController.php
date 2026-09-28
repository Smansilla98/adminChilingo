<?php

namespace App\Http\Controllers;

use App\Domain\Agenda\BloqueService;
use App\Models\Bloque;
use App\Models\BloqueHorario;
use Illuminate\Http\Request;

class BloqueHorarioController extends Controller
{
    public function store(Request $request, Bloque $bloque, BloqueService $bloques)
    {
        $this->authorize('update', $bloque);
        $bloques->agregarHorario($bloque, $request->validate($bloques->reglasHorario()));

        return redirect()->route('bloques.edit', $bloque)->with('success', 'Horario agregado.');
    }

    public function destroy(BloqueHorario $bloqueHorario)
    {
        $bloque = $bloqueHorario->bloque;
        $this->authorize('update', $bloque);
        $bloqueHorario->delete();

        return redirect()->route('bloques.edit', $bloque)->with('success', 'Horario eliminado.');
    }
}
