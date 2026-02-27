<?php

namespace App\Http\Controllers;

use App\Models\Sede;
use Illuminate\Http\Request;

class SedeController extends Controller
{
    public function index()
    {
        $sedes = Sede::orderBy('nombre')->paginate(20);
        return view('sedes.index', compact('sedes'));
    }

    public function create()
    {
        return view('sedes.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255|unique:sedes,nombre',
            'direccion' => 'nullable|string|max:255',
            'tipo_propiedad' => 'nullable|string|in:propia,alquilada,compartida,otro',
            'costo_alquiler_mensual' => 'nullable|numeric|min:0',
            'activo' => 'boolean',
        ]);
        $validated['activo'] = $request->has('activo') ? true : true;
        if (empty($validated['tipo_propiedad'])) {
            $validated['tipo_propiedad'] = 'alquilada';
        }

        Sede::create($validated);

        return redirect()->route('sedes.index')
            ->with('success', 'Sede creada exitosamente.');
    }

    public function show(Sede $sede)
    {
        $sede->load(['bloques.profesor', 'alumnos', 'eventos']);
        return view('sedes.show', compact('sede'));
    }

    public function edit(Sede $sede)
    {
        return view('sedes.edit', compact('sede'));
    }

    public function update(Request $request, Sede $sede)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255|unique:sedes,nombre,' . $sede->id,
            'direccion' => 'nullable|string|max:255',
            'tipo_propiedad' => 'nullable|string|in:propia,alquilada,compartida,otro',
            'costo_alquiler_mensual' => 'nullable|numeric|min:0',
            'activo' => 'boolean',
        ]);
        $validated['activo'] = $request->has('activo') ? true : false;
        if (empty($validated['tipo_propiedad'])) {
            $validated['tipo_propiedad'] = 'alquilada';
        }

        $sede->update($validated);

        return redirect()->route('sedes.index')
            ->with('success', 'Sede actualizada exitosamente.');
    }

    public function destroy(Sede $sede)
    {
        $sede->delete();

        return redirect()->route('sedes.index')
            ->with('success', 'Sede eliminada exitosamente.');
    }
}
