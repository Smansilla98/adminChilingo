<?php

namespace App\Http\Controllers;

use App\Models\Cuota;
use Illuminate\Http\Request;

class CuotaController extends Controller
{
    public function index(Request $request)
    {
        $query = Cuota::query();
        if ($request->filled('año')) {
            $query->where('año', $request->año);
        }
        $cuotas = $query->orderBy('año', 'desc')->orderBy('mes')->paginate(20);
        return view('cuotas.index', compact('cuotas'));
    }

    public function create()
    {
        return view('cuotas.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'año' => 'required|integer|min:2020|max:2030',
            'mes' => 'nullable|integer|min:1|max:12',
            'monto' => 'required|numeric|min:0',
            'descripcion' => 'nullable|string|max:500',
            'activo' => 'boolean',
        ]);
        $validated['activo'] = $request->has('activo');
        Cuota::create($validated);
        return redirect()->route('cuotas.index')->with('success', 'Cuota creada.');
    }

    public function show(Cuota $cuota)
    {
        $cuota->loadCount('pagoDetalles');
        return view('cuotas.show', compact('cuota'));
    }

    public function edit(Cuota $cuota)
    {
        return view('cuotas.edit', compact('cuota'));
    }

    public function update(Request $request, Cuota $cuota)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'año' => 'required|integer|min:2020|max:2030',
            'mes' => 'nullable|integer|min:1|max:12',
            'monto' => 'required|numeric|min:0',
            'descripcion' => 'nullable|string|max:500',
            'activo' => 'boolean',
        ]);
        $validated['activo'] = $request->has('activo');
        $cuota->update($validated);
        return redirect()->route('cuotas.index')->with('success', 'Cuota actualizada.');
    }

    public function destroy(Cuota $cuota)
    {
        $cuota->delete();
        return redirect()->route('cuotas.index')->with('success', 'Cuota eliminada.');
    }
}
