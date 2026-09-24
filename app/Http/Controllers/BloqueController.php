<?php

namespace App\Http\Controllers;

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

        $validated['activo'] = $request->boolean('activo');
        $validated['tambores'] = $request->input('tambores') ? array_values($request->input('tambores')) : null;
        $this->asegurarSedeGestionable((int) $validated['sede_id']);

        $bloque = Bloque::create($validated);
        $bloque->syncProfesorTitularEnPivot();

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

    public function update(Request $request, Bloque $bloque)
    {
        $this->authorize('update', $bloque);
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

        $validated['activo'] = $request->boolean('activo');
        $validated['tambores'] = $request->input('tambores') ? array_values($request->input('tambores')) : null;
        if ((int) $validated['sede_id'] !== (int) $bloque->sede_id) {
            $this->asegurarSedeGestionable((int) $validated['sede_id']);
        }

        $bloque->update($validated);
        $bloque->syncProfesorTitularEnPivot();

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

    private function asegurarSedeGestionable(int $sedeId): void
    {
        if (! auth()->user()->acceso()->puedeEnSede('bloques.manage', $sedeId)) {
            abort(403, 'No podés gestionar bloques en esa sede.');
        }
    }
}
