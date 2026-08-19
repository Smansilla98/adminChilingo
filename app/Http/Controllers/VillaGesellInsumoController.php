<?php

namespace App\Http\Controllers;

use App\Models\VillaGesellInsumo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VillaGesellInsumoController extends Controller
{
    public function index(): View
    {
        $insumos = VillaGesellInsumo::query()->orderBy('categoria')->orderBy('nombre')->get();
        $total = $insumos->sum(fn (VillaGesellInsumo $i) => $i->costoTotal());

        return view('villa-gesell.insumos.index', compact('insumos', 'total'));
    }

    public function create(): View
    {
        return view('villa-gesell.insumos.create', ['insumo' => new VillaGesellInsumo(['cantidad' => 1])]);
    }

    public function store(Request $request): RedirectResponse
    {
        VillaGesellInsumo::query()->create($this->validated($request));

        return redirect()->route('villa-gesell.insumos.index')->with('success', 'Insumo registrado.');
    }

    public function edit(VillaGesellInsumo $insumo): View
    {
        return view('villa-gesell.insumos.edit', compact('insumo'));
    }

    public function update(Request $request, VillaGesellInsumo $insumo): RedirectResponse
    {
        $insumo->update($this->validated($request));

        return redirect()->route('villa-gesell.insumos.index')->with('success', 'Insumo actualizado.');
    }

    public function destroy(VillaGesellInsumo $insumo): RedirectResponse
    {
        $insumo->delete();

        return redirect()->route('villa-gesell.insumos.index')->with('success', 'Insumo eliminado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:160'],
            'categoria' => ['required', Rule::in(array_keys(VillaGesellInsumo::CATEGORIAS))],
            'cantidad' => ['required', 'numeric', 'min:0'],
            'unidad' => ['nullable', 'string', 'max:20'],
            'costo_unitario' => ['required', 'numeric', 'min:0'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
