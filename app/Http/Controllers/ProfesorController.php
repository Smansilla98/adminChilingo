<?php

namespace App\Http\Controllers;

use App\Models\Bloque;
use App\Models\Profesor;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

class ProfesorController extends Controller
{
    public function index()
    {
        try {
            $profesores = Profesor::withCount('bloques')->orderBy('nombre')->paginate(20);
        } catch (QueryException $e) {
            $profesores = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
        }
        return view('profesores.index', compact('profesores'));
    }

    public function create()
    {
        $bloquesParaAsignar = Bloque::with('sede')->where('activo', true)->orderBy('nombre')->get();

        return view('profesores.create', compact('bloquesParaAsignar'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|unique:profesores,email',
            'activo' => 'boolean',
        ]);

        $validated['activo'] = $request->boolean('activo');

        $profesor = Profesor::create($validated);
        $profesor->sincronizarAsignacionesBloques($this->filasAsignacionesBloquesDesdeRequest($request));

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
        $profesor->load('bloques');
        $bloquesParaAsignar = Bloque::with('sede')->where('activo', true)->orderBy('nombre')->get();

        return view('profesores.edit', compact('profesor', 'bloquesParaAsignar'));
    }

    public function update(Request $request, Profesor $profesor)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|unique:profesores,email,' . $profesor->id,
            'activo' => 'boolean',
        ]);

        $validated['activo'] = $request->boolean('activo');

        $profesor->update($validated);
        $profesor->sincronizarAsignacionesBloques($this->filasAsignacionesBloquesDesdeRequest($request));

        return redirect()->route('profesores.index')
            ->with('success', 'Profesor actualizado exitosamente.');
    }

    public function destroy(Profesor $profesor)
    {
        $profesor->delete();

        return redirect()->route('profesores.index')
            ->with('success', 'Profesor eliminado exitosamente.');
    }

    /**
     * @return array<int, array{bloque_id: int, rol: string}>
     */
    private function filasAsignacionesBloquesDesdeRequest(Request $request): array
    {
        $filas = [];
        foreach ($request->input('asignaciones', []) as $key => $row) {
            if (! is_array($row)) {
                continue;
            }
            if (empty($row['asignado'])) {
                continue;
            }
            $bid = (int) ($row['bloque_id'] ?? $key);
            if ($bid <= 0) {
                continue;
            }
            $rol = $row['rol'] ?? 'ayudante';
            if (! in_array($rol, Profesor::ROLES_BLOQUE, true)) {
                $rol = 'ayudante';
            }
            $filas[] = ['bloque_id' => $bid, 'rol' => $rol];
        }

        return $filas;
    }
}
