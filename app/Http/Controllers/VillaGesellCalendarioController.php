<?php

namespace App\Http\Controllers;

use App\Domain\VillaGesell\VillaGesellAdmin;
use App\Models\VillaGesellDia;
use App\Models\VillaGesellTocada;
use App\Services\VillaGesellGiraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VillaGesellCalendarioController extends Controller
{
    public function __construct(private VillaGesellGiraService $gira) {}

    public function index(): View
    {
        $this->gira->asegurarDias();
        $config = $this->gira->config();
        $dias = VillaGesellDia::query()
            ->with('tocadas')
            ->whereBetween('fecha', [$config->fecha_inicio, $config->fecha_fin])
            ->orderBy('fecha')
            ->get();

        return view('villa-gesell.calendario.index', compact('config', 'dias'));
    }

    public function updateDia(Request $request, VillaGesellDia $dia): RedirectResponse
    {
        $data = $request->validate([
            'notas' => ['nullable', 'string', 'max:400'],
        ]);
        $dia->update($data);

        return back()->with('success', 'Notas del día guardadas.');
    }

    public function generarSlots(Request $request, VillaGesellDia $dia, VillaGesellAdmin $admin): RedirectResponse
    {
        $data = $request->validate(['cantidad' => ['required', 'integer', 'min:1', 'max:12']]);
        $admin->generarTocadas($dia, (int) $data['cantidad']);

        return back()->with('success', 'Se generaron '.$data['cantidad'].' fechas en el día.');
    }

    public function storeTocada(Request $request, VillaGesellDia $dia, VillaGesellAdmin $admin): RedirectResponse
    {
        $admin->agregarTocada($dia, $request->validate($admin->reglasTocada()));

        return back()->with('success', 'Fecha agregada.');
    }

    public function updateTocada(Request $request, VillaGesellTocada $tocada, VillaGesellAdmin $admin): RedirectResponse
    {
        $data = $request->validate($admin->reglasTocada());
        $data['orden'] = isset($data['orden']) ? (int) $data['orden'] : null;
        $tocada->update($data);

        return back()->with('success', 'Fecha actualizada.');
    }

    public function destroyTocada(VillaGesellTocada $tocada): RedirectResponse
    {
        $tocada->delete();

        return back()->with('success', 'Fecha eliminada.');
    }
}
