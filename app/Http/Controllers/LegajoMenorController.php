<?php

namespace App\Http\Controllers;

use App\Domain\Personas\LegajoMenorService;
use App\Models\LegajoMenor;
use App\Models\Persona;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Legajo de menor (web): tutor, uso de imagen y apto médico. */
class LegajoMenorController extends Controller
{
    public function show(Persona $persona, LegajoMenorService $legajos): View
    {
        $this->authorize('view', $persona);

        return view('personas.legajo', [
            'persona' => $persona,
            'legajo' => LegajoMenor::query()->firstOrNew(['persona_id' => $persona->id]),
            'esMenor' => $legajos->esMenor($persona),
        ]);
    }

    public function update(Request $request, Persona $persona, LegajoMenorService $legajos): RedirectResponse
    {
        $this->authorize('update', $persona);
        $datos = $request->validate($legajos->reglas($persona));
        $legajos->guardar($persona, $datos, $request->file('apto_medico'), $request->user());

        return redirect()->route('personas.legajo', $persona)->with('success', 'Legajo guardado.');
    }

    public function apto(Persona $persona): Response
    {
        $this->authorize('view', $persona);
        $legajo = LegajoMenor::query()->where('persona_id', $persona->id)->firstOrFail();
        abort_if(! $legajo->apto_medico_path, 404);

        return LegajoMenorService::respuestaApto($legajo);
    }
}
