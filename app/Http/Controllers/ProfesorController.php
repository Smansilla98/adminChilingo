<?php

namespace App\Http\Controllers;

use App\Models\Profesor;
use Illuminate\Http\Request;

class ProfesorController extends Controller
{
    public function index()
    {
        $profesores = Profesor::orderBy('nombre')->paginate(20);
        return view('profesores.index', compact('profesores'));
    }

    public function create()
    {
        return view('profesores.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|unique:profesores,email',
            'activo' => 'boolean',
        ]);

        $validated['activo'] = $request->has('activo') ? true : true;

        Profesor::create($validated);

        return redirect()->route('profesores.index')
            ->with('success', 'Profesor creado exitosamente.');
    }

    public function show(Profesor $profesor)
    {
        $profesor->load(['bloques.sede', 'eventos']);
        return view('profesores.show', compact('profesor'));
    }

    public function edit(Profesor $profesor)
    {
        return view('profesores.edit', compact('profesor'));
    }

    public function update(Request $request, Profesor $profesor)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|unique:profesores,email,' . $profesor->id,
            'activo' => 'boolean',
        ]);

        $validated['activo'] = $request->has('activo') ? true : false;

        $profesor->update($validated);

        return redirect()->route('profesores.index')
            ->with('success', 'Profesor actualizado exitosamente.');
    }

    public function destroy(Profesor $profesor)
    {
        $profesor->delete();

        return redirect()->route('profesores.index')
            ->with('success', 'Profesor eliminado exitosamente.');
    }
}
