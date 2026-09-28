<?php

namespace App\Http\Controllers;

use App\Domain\Agenda\BloqueService;
use App\Domain\Datos\EliminacionSegura;
use App\Models\Bloque;
use App\Models\Profesor;
use App\Models\Sede;
use Illuminate\Http\Request;

class BloqueController extends Controller
{
    public function index()
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        $query = Bloque::with(['profesor', 'sede'])->orderBy('año')->orderBy('nombre');
        $user->acceso()->alcance('bloques.view')->aplicarBloques($query);

        $bloques = $query->paginate(20);

        return view('bloques.index', compact('bloques'));
    }

    public function create()
    {
        $this->authorize('create', Bloque::class);
        $profesores = Profesor::where('activo', true)->get();
        $sedes = $this->sedesGestionables();
        $tamboresDisponibles = Bloque::TAMBORES_DISPONIBLES;

        return view('bloques.create', compact('profesores', 'sedes', 'tamboresDisponibles'));
    }

    public function store(Request $request, BloqueService $bloques)
    {
        $validated = $request->validate($bloques->reglas());
        $validated['activo'] = $request->boolean('activo');
        $bloques->crear($validated, $request->user());

        return redirect()->route('bloques.index')
            ->with('success', 'Bloque creado exitosamente.');
    }

    public function show(Bloque $bloque)
    {
        $this->authorize('view', $bloque);
        $bloque->load(['profesor', 'profesores', 'sede', 'alumnos', 'eventos']);

        return view('bloques.show', compact('bloque'));
    }

    public function edit(Bloque $bloque)
    {
        $this->authorize('update', $bloque);
        $bloque->load('horarios');
        $profesores = Profesor::where('activo', true)->get();
        $sedes = $this->sedesGestionables();
        $tamboresDisponibles = Bloque::TAMBORES_DISPONIBLES;

        return view('bloques.edit', compact('bloque', 'profesores', 'sedes', 'tamboresDisponibles'));
    }

    public function update(Request $request, Bloque $bloque, BloqueService $bloques)
    {
        $this->authorize('update', $bloque);
        $validated = $request->validate($bloques->reglas());
        $validated['activo'] = $request->boolean('activo');
        $bloques->actualizar($bloque, $validated, $request->user());

        return redirect()->route('bloques.index')
            ->with('success', 'Bloque actualizado exitosamente.');
    }

    public function destroy(Bloque $bloque, EliminacionSegura $eliminacion)
    {
        $this->authorize('delete', $bloque);
        $eliminacion->verificar($bloque);
        $bloque->delete();

        return redirect()->route('bloques.index')
            ->with('success', 'Bloque eliminado exitosamente.');
    }

    /** Sedes donde el usuario puede crear o mover bloques. */
    private function sedesGestionables()
    {
        $query = Sede::where('activo', true)->orderBy('nombre');
        $alcance = auth()->user()->acceso()->alcance('bloques.manage');

        return $alcance->aplicarPorSede($query, 'id')->get();
    }
}
