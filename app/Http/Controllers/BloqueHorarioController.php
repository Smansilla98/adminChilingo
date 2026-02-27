<?php

namespace App\Http\Controllers;

use App\Models\Bloque;
use App\Models\BloqueHorario;
use Illuminate\Http\Request;

class BloqueHorarioController extends Controller
{
    public function store(Request $request, Bloque $bloque)
    {
        $validated = $request->validate([
            'dia_semana' => 'required|integer|min:1|max:7',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fin' => 'required|date_format:H:i|after:hora_inicio',
        ]);
        $bloque->horarios()->create([
            'dia_semana' => $validated['dia_semana'],
            'hora_inicio' => $validated['hora_inicio'] . ':00',
            'hora_fin' => $validated['hora_fin'] . ':00',
        ]);
        return redirect()->route('bloques.edit', $bloque)->with('success', 'Horario agregado.');
    }

    public function destroy(BloqueHorario $bloqueHorario)
    {
        $bloque = $bloqueHorario->bloque;
        $bloqueHorario->delete();
        return redirect()->route('bloques.edit', $bloque)->with('success', 'Horario eliminado.');
    }
}
