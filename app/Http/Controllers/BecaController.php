<?php

namespace App\Http\Controllers;

use App\Domain\Finanzas\BecaService;
use App\Http\Requests\BecaRequest;
use App\Http\Requests\BecaUpdateRequest;
use App\Models\Alumno;
use App\Models\Beca;
use App\Models\Persona;
use Illuminate\Http\RedirectResponse;

class BecaController extends Controller
{
    public function store(BecaRequest $request, Persona $persona, BecaService $becas): RedirectResponse
    {
        $data = $request->validated();
        $alumno = Alumno::query()->where('persona_id', $persona->id)->findOrFail($data['alumno_id']);
        $this->authorize('gestionarBecas', $alumno);

        $becas->otorgar($alumno, $data, $request->user());

        return redirect()->route('personas.show', $persona)->with('success', 'Beca registrada. Se aplica al estado de cuenta desde la fecha de inicio.');
    }

    public function update(BecaUpdateRequest $request, Beca $beca, BecaService $becas): RedirectResponse
    {
        $becas->actualizar($beca, $request->validated());

        return back()->with('success', 'Beca actualizada.');
    }
}
