<?php

namespace App\Http\Controllers;

use App\Models\VillaGesellInscripto;
use App\Services\VillaGesellGiraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VillaGesellController extends Controller
{
    public function __construct(private VillaGesellGiraService $gira) {}

    public function index(): View
    {
        $this->gira->asegurarDias();
        $config = $this->gira->config();
        $plan = $this->gira->plan();
        $inscriptos = VillaGesellInscripto::query()
            ->with('alumno')
            ->orderByRaw('lista_espera asc')
            ->orderByRaw('plaza is null')
            ->orderBy('plaza')
            ->get();

        return view('villa-gesell.index', compact('config', 'plan', 'inscriptos'));
    }

    public function updateConfig(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'cupo_maximo' => ['required', 'integer', 'min:1', 'max:500'],
            'aporte_esperado' => ['required', 'numeric', 'min:0'],
            'notas' => ['nullable', 'string', 'max:4000'],
        ]);

        $config = $this->gira->config();
        $plazas = $this->gira->plazasOcupadas();
        $maxPlaza = $plazas === [] ? 0 : max($plazas);
        if ($data['cupo_maximo'] < $maxPlaza) {
            return back()->withErrors([
                'cupo_maximo' => "Hay una plaza ocupada n.º {$maxPlaza}. Bajá esa plaza antes de reducir el cupo.",
            ])->withInput();
        }

        $config->fill($data)->save();
        $this->gira->asegurarDias();

        return redirect()->route('villa-gesell.index')->with('success', 'Datos de la gira actualizados.');
    }

    public function generarDias(): RedirectResponse
    {
        $n = $this->gira->asegurarDias();

        return redirect()->route('villa-gesell.calendario')->with(
            'success',
            $n > 0 ? "Se agregaron {$n} días al calendario." : 'El calendario ya tenía todos los días de la gira.'
        );
    }
}
