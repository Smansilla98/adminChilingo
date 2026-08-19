<?php

namespace App\Http\Controllers;

use App\Models\Alumno;
use App\Models\VillaGesellInscripto;
use App\Services\VillaGesellGiraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VillaGesellInscriptoController extends Controller
{
    public function __construct(private VillaGesellGiraService $gira) {}

    public function index(): View
    {
        $config = $this->gira->config();
        $inscriptos = VillaGesellInscripto::query()
            ->with('alumno.sede')
            ->orderByRaw('lista_espera asc')
            ->orderByRaw('plaza is null')
            ->orderBy('plaza')
            ->get();

        return view('villa-gesell.inscriptos.index', compact('inscriptos', 'config'));
    }

    public function create(): View
    {
        $config = $this->gira->config();
        $alumnos = $this->alumnosDisponibles();
        $inscripto = new VillaGesellInscripto([
            'estado_pago' => 'pendiente',
            'monto_esperado' => $config->aporte_esperado,
            'fecha_desde' => $config->fecha_inicio,
            'fecha_hasta' => $config->fecha_fin,
            'plaza' => $this->gira->plazaDisponible(),
        ]);

        return view('villa-gesell.inscriptos.create', compact('alumnos', 'config', 'inscripto'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $this->gira->validarPlaza($data['lista_espera'] ? null : $data['plaza'], null);
        if ($data['lista_espera']) {
            $data['plaza'] = null;
        }

        VillaGesellInscripto::query()->create($data);

        return redirect()->route('villa-gesell.inscriptos.index')->with('success', 'Alumno inscripto en la gira.');
    }

    public function edit(VillaGesellInscripto $inscripto): View
    {
        $inscripto->load('alumno');
        $config = $this->gira->config();
        $alumnos = $this->alumnosDisponibles($inscripto->alumno_id);

        return view('villa-gesell.inscriptos.edit', compact('inscripto', 'alumnos', 'config'));
    }

    public function update(Request $request, VillaGesellInscripto $inscripto): RedirectResponse
    {
        $data = $this->validated($request, $inscripto->id);
        $this->gira->validarPlaza($data['lista_espera'] ? null : $data['plaza'], $inscripto->id);
        if ($data['lista_espera']) {
            $data['plaza'] = null;
        }
        $inscripto->update($data);

        return redirect()->route('villa-gesell.inscriptos.index')->with('success', 'Inscripción actualizada.');
    }

    public function destroy(VillaGesellInscripto $inscripto): RedirectResponse
    {
        $inscripto->delete();

        return redirect()->route('villa-gesell.inscriptos.index')->with('success', 'Inscripción eliminada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $exceptId = null): array
    {
        $config = $this->gira->config();
        $data = $request->validate([
            'alumno_id' => [
                'required',
                'exists:alumnos,id',
                Rule::unique('villa_gesell_inscriptos', 'alumno_id')->ignore($exceptId),
            ],
            'estado_pago' => ['required', Rule::in(array_keys(VillaGesellInscripto::ESTADOS_PAGO))],
            'monto_esperado' => ['required', 'numeric', 'min:0'],
            'monto_pagado' => ['required', 'numeric', 'min:0'],
            'plaza' => ['nullable', 'integer', 'min:1', 'max:'.$config->cupo_maximo],
            'lista_espera' => ['sometimes', 'boolean'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'talle_remera' => ['nullable', Rule::in(array_keys(VillaGesellInscripto::TALLES))],
            'tambor_principal' => ['nullable', Rule::in(VillaGesellInscripto::TAMBORES)],
            'tambor_secundario' => ['nullable', Rule::in(VillaGesellInscripto::TAMBORES)],
            'tambor_terciario' => ['nullable', Rule::in(VillaGesellInscripto::TAMBORES)],
            'tambor_principal_origen' => ['nullable', Rule::in(array_keys(VillaGesellInscripto::ORIGENES_TAMBOR))],
            'tambor_secundario_origen' => ['nullable', Rule::in(array_keys(VillaGesellInscripto::ORIGENES_TAMBOR))],
            'tambor_terciario_origen' => ['nullable', Rule::in(array_keys(VillaGesellInscripto::ORIGENES_TAMBOR))],
            'notas' => ['nullable', 'string', 'max:4000'],
        ]);
        $data['lista_espera'] = $request->boolean('lista_espera');
        $data['plaza'] = $data['plaza'] !== null ? (int) $data['plaza'] : null;

        return $data;
    }

    private function alumnosDisponibles(?int $keepId = null)
    {
        $ocupados = VillaGesellInscripto::query()
            ->when($keepId, fn ($q) => $q->where('alumno_id', '!=', $keepId))
            ->pluck('alumno_id');

        return Alumno::query()
            ->whereNotIn('id', $ocupados)
            ->where(function ($q) use ($keepId) {
                $q->where('activo', true);
                if ($keepId) {
                    $q->orWhere('id', $keepId);
                }
            })
            ->orderBy('nombre_apellido')
            ->get();
    }
}
