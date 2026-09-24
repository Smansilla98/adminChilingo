<?php

namespace App\Http\Controllers;

use App\Models\Alumno;
use App\Models\Beca;
use App\Models\Persona;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BecaController extends Controller
{
    public function store(Request $request, Persona $persona): RedirectResponse
    {
        $data = $this->validar($request);
        $alumno = Alumno::query()->where('persona_id', $persona->id)->findOrFail($data['alumno_id']);
        $this->authorize('gestionarBecas', $alumno);

        $alumno->becas()->create($data + ['otorgada_por' => $request->user()->id, 'estado' => 'activa']);

        return redirect()->route('personas.show', $persona)->with('success', 'Beca registrada. Se aplica al estado de cuenta desde la fecha de inicio.');
    }

    public function update(Request $request, Beca $beca): RedirectResponse
    {
        $this->authorize('gestionarBecas', $beca->alumno);
        $data = $request->validate([
            'estado' => 'required|in:'.implode(',', array_keys(Beca::ESTADOS)),
            'fecha_fin' => 'nullable|date|after_or_equal:'.$beca->fecha_inicio->toDateString(),
            'observaciones' => 'nullable|string|max:2000',
        ]);
        $beca->update($data);

        return back()->with('success', 'Beca actualizada.');
    }

    /** @return array<string, mixed> */
    private function validar(Request $request): array
    {
        $data = $request->validate([
            'alumno_id' => 'required|integer',
            'tipo' => 'required|in:'.implode(',', array_keys(Beca::TIPOS)),
            'porcentaje' => 'nullable|required_if:tipo,porcentaje|numeric|min:1|max:100',
            'monto' => 'nullable|required_if:tipo,monto_fijo|numeric|min:1',
            'bloque_id' => 'nullable|exists:bloques,id',
            'sede_id' => 'nullable|exists:sedes,id',
            'motivo' => 'nullable|string|max:255',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'observaciones' => 'nullable|string|max:2000',
        ]);
        if ($data['tipo'] !== 'porcentaje') {
            $data['porcentaje'] = null;
        }
        if ($data['tipo'] !== 'monto_fijo') {
            $data['monto'] = null;
        }

        return $data;
    }
}
