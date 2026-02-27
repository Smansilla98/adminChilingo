<?php

namespace App\Http\Controllers;

use App\Models\Gasto;
use App\Models\Sede;
use App\Models\Bloque;
use Illuminate\Http\Request;

class GastoController extends Controller
{
    public function index(Request $request)
    {
        $query = Gasto::with(['sede', 'bloque', 'creador'])->orderByDesc('fecha')->orderByDesc('id');

        if ($request->filled('sede_id')) {
            $query->where('sede_id', $request->sede_id);
        }
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }
        if ($request->filled('desde')) {
            $query->where('fecha', '>=', $request->desde);
        }
        if ($request->filled('hasta')) {
            $query->where('fecha', '<=', $request->hasta);
        }

        $gastos = $query->paginate(25);
        $sedes = Sede::orderBy('nombre')->get();

        return view('gastos.index', compact('gastos', 'sedes'));
    }

    public function create(Request $request)
    {
        $sedes = Sede::orderBy('nombre')->get();
        $bloques = Bloque::where('activo', true)->orderBy('sede_id')->orderBy('nombre')->get();
        return view('gastos.create', compact('sedes', 'bloques'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sede_id' => 'nullable|exists:sedes,id',
            'bloque_id' => 'nullable|exists:bloques,id',
            'fecha' => 'required|date',
            'tipo' => 'required|string|in:' . implode(',', array_keys(Gasto::TIPOS)),
            'subtipo' => 'nullable|string|max:40',
            'descripcion' => 'nullable|string|max:255',
            'monto' => 'required|numeric|min:0',
            'proveedor' => 'nullable|string|max:255',
            'notas' => 'nullable|string',
        ]);
        $validated['created_by'] = auth()->id();
        Gasto::create($validated);
        return redirect()->route('gastos.index')->with('success', 'Gasto registrado.');
    }

    public function show(Gasto $gasto)
    {
        $gasto->load(['sede', 'bloque', 'creador']);
        return view('gastos.show', compact('gasto'));
    }

    public function edit(Gasto $gasto)
    {
        $sedes = Sede::orderBy('nombre')->get();
        $bloques = Bloque::where('activo', true)->orderBy('sede_id')->orderBy('nombre')->get();
        return view('gastos.edit', compact('gasto', 'sedes', 'bloques'));
    }

    public function update(Request $request, Gasto $gasto)
    {
        $validated = $request->validate([
            'sede_id' => 'nullable|exists:sedes,id',
            'bloque_id' => 'nullable|exists:bloques,id',
            'fecha' => 'required|date',
            'tipo' => 'required|string|in:' . implode(',', array_keys(Gasto::TIPOS)),
            'subtipo' => 'nullable|string|max:40',
            'descripcion' => 'nullable|string|max:255',
            'monto' => 'required|numeric|min:0',
            'proveedor' => 'nullable|string|max:255',
            'notas' => 'nullable|string',
        ]);
        $gasto->update($validated);
        return redirect()->route('gastos.index')->with('success', 'Gasto actualizado.');
    }

    public function destroy(Gasto $gasto)
    {
        $gasto->delete();
        return redirect()->route('gastos.index')->with('success', 'Gasto eliminado.');
    }
}
