<?php

namespace App\Http\Controllers;

use App\Domain\Agenda\SedeService;
use App\Domain\Datos\EliminacionSegura;
use App\Models\Sede;
use Illuminate\Http\Request;

class SedeController extends Controller
{
    public function index()
    {
        $query = Sede::orderBy('nombre');
        auth()->user()->acceso()->alcance('sedes.view')->aplicarPorSede($query, 'id');
        $sedes = $query->paginate(20);

        return view('sedes.index', compact('sedes'));
    }

    public function create()
    {
        $this->authorize('create', Sede::class);

        return view('sedes.create');
    }

    public function store(Request $request, SedeService $sedes)
    {
        $this->authorize('create', Sede::class);
        $validated = $request->validate($sedes->reglas());
        $validated['activo'] = $request->boolean('activo');
        $sedes->crear($validated);

        return redirect()->route('sedes.index')
            ->with('success', 'Sede creada exitosamente.');
    }

    public function show(Sede $sede)
    {
        $this->authorize('view', $sede);
        $sede->load(['bloques.profesor', 'alumnos', 'eventos']);

        return view('sedes.show', compact('sede'));
    }

    public function edit(Sede $sede)
    {
        $this->authorize('update', $sede);

        return view('sedes.edit', compact('sede'));
    }

    public function update(Request $request, Sede $sede, SedeService $sedes)
    {
        $this->authorize('update', $sede);
        $validated = $request->validate($sedes->reglas($sede));
        $validated['activo'] = $request->has('activo') ? true : false;
        $sedes->actualizar($sede, $validated);

        return redirect()->route('sedes.index')
            ->with('success', 'Sede actualizada exitosamente.');
    }

    public function destroy(Sede $sede, EliminacionSegura $eliminacion)
    {
        $this->authorize('delete', $sede);
        $eliminacion->verificar($sede);
        $sede->delete();

        return redirect()->route('sedes.index')
            ->with('success', 'Sede eliminada exitosamente.');
    }
}
