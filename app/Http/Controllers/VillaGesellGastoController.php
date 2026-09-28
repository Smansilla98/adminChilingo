<?php

namespace App\Http\Controllers;

use App\Domain\VillaGesell\VillaGesellAdmin;
use App\Models\VillaGesellGasto;
use App\Services\VillaGesellGiraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VillaGesellGastoController extends Controller
{
    public function __construct(private VillaGesellGiraService $gira) {}

    public function index(): View
    {
        $gastos = VillaGesellGasto::query()->orderBy('tipo')->orderBy('concepto')->get();
        $plan = $this->gira->plan();
        $dias = $plan['dias'];

        return view('villa-gesell.gastos.index', compact('gastos', 'plan', 'dias'));
    }

    public function create(): View
    {
        return view('villa-gesell.gastos.create', ['gasto' => new VillaGesellGasto(['modo' => 'por_dia', 'tipo' => 'diario'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        VillaGesellGasto::query()->create($this->validated($request));

        return redirect()->route('villa-gesell.gastos.index')->with('success', 'Gasto de la gira registrado.');
    }

    public function edit(VillaGesellGasto $gasto): View
    {
        return view('villa-gesell.gastos.edit', compact('gasto'));
    }

    public function update(Request $request, VillaGesellGasto $gasto): RedirectResponse
    {
        $gasto->update($this->validated($request));

        return redirect()->route('villa-gesell.gastos.index')->with('success', 'Gasto actualizado.');
    }

    public function destroy(VillaGesellGasto $gasto): RedirectResponse
    {
        $gasto->delete();

        return redirect()->route('villa-gesell.gastos.index')->with('success', 'Gasto eliminado.');
    }

    public function plan(): View
    {
        $config = $this->gira->config();
        $plan = $this->gira->plan();

        return view('villa-gesell.plan.index', compact('config', 'plan'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $admin = app(VillaGesellAdmin::class);

        return $admin->normalizarGasto($request->validate($admin->reglasGasto()));
    }
}
