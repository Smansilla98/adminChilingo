<?php

namespace App\Http\Controllers;

use App\Domain\VillaGesell\VillaGesellAdmin;
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

    public function updateConfig(Request $request, VillaGesellAdmin $admin): RedirectResponse
    {
        $admin->actualizarConfig($request->validate($admin->reglasConfig()));

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
