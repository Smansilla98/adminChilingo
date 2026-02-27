<?php

namespace App\Http\Controllers;

use App\Models\Bloque;
use App\Models\Profesor;
use App\Models\Sede;
use Illuminate\Http\Request;

class BloqueController extends Controller
{
    public function index()
    {
        $bloques = Bloque::with(['profesor', 'sede'])->orderBy('año')->orderBy('nombre')->paginate(20);
        return view('bloques.index', compact('bloques'));
    }

    public function create()
    {
        $profesores = Profesor::where('activo', true)->get();
        $sedes = Sede::where('activo', true)->get();
        $tamboresDisponibles = Bloque::TAMBORES_DISPONIBLES;
        return view('bloques.create', compact('profesores', 'sedes', 'tamboresDisponibles'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'año' => 'required|integer|min:1|max:6',
            'profesor_id' => 'nullable|exists:profesores,id',
            'corresponde_a' => 'nullable|string|max:255',
            'sede_id' => 'required|exists:sedes,id',
            'cantidad_max_alumnos' => 'required|integer|min:1',
            'tambores' => 'nullable|array',
            'tambores.*' => 'string|max:100',
            'activo' => 'boolean',
        ]);

        $validated['activo'] = $request->has('activo') ? true : true;
        $validated['tambores'] = $request->input('tambores') ? array_values($request->input('tambores')) : null;

        Bloque::create($validated);

        return redirect()->route('bloques.index')
            ->with('success', 'Bloque creado exitosamente.');
    }

    public function show(Bloque $bloque)
    {
        $bloque->load(['profesor', 'sede', 'alumnos', 'eventos']);
        return view('bloques.show', compact('bloque'));
    }

    public function edit(Bloque $bloque)
    {
        $bloque->load('horarios');
        $profesores = Profesor::where('activo', true)->get();
        $sedes = Sede::where('activo', true)->get();
        $tamboresDisponibles = Bloque::TAMBORES_DISPONIBLES;
        return view('bloques.edit', compact('bloque', 'profesores', 'sedes', 'tamboresDisponibles'));
    }

    public function update(Request $request, Bloque $bloque)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'año' => 'required|integer|min:1|max:6',
            'profesor_id' => 'nullable|exists:profesores,id',
            'corresponde_a' => 'nullable|string|max:255',
            'sede_id' => 'required|exists:sedes,id',
            'cantidad_max_alumnos' => 'required|integer|min:1',
            'tambores' => 'nullable|array',
            'tambores.*' => 'string|max:100',
            'activo' => 'boolean',
        ]);

        $validated['activo'] = $request->has('activo') ? true : false;
        $validated['tambores'] = $request->input('tambores') ? array_values($request->input('tambores')) : null;

        $bloque->update($validated);

        return redirect()->route('bloques.index')
            ->with('success', 'Bloque actualizado exitosamente.');
    }

    public function destroy(Bloque $bloque)
    {
        $bloque->delete();

        return redirect()->route('bloques.index')
            ->with('success', 'Bloque eliminado exitosamente.');
    }
}
