<?php

namespace App\Http\Controllers;

use App\Models\Bloque;
use App\Models\Profesor;
use App\Models\Sede;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

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
        $sedes = Sede::where('activo', true)->orderBy('nombre')->get();

        return view('profesores.create', compact('bloquesParaAsignar', 'sedes'));
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
        $profesor->sincronizarRolesSede($this->filasSedeRolesDesdeRequest($request));
        $profesor->sincronizarRolesUsuario();

        return redirect()->route('profesores.index')
            ->with('success', 'Profesor creado exitosamente.');
    }

    public function show(Profesor $profesor)
    {
        $profesor->load(['bloques.sede', 'sedesConRol', 'eventos', 'user', 'coordinadorAreas']);
        $alumnoPerfil = $profesor->alumnoPerfil();

        return view('profesores.show', compact('profesor', 'alumnoPerfil'));
    }

    public function edit(Profesor $profesor)
    {
        $profesor->load(['bloques', 'sedesConRol']);
        $bloquesParaAsignar = Bloque::with('sede')->where('activo', true)->orderBy('nombre')->get();
        $sedes = Sede::where('activo', true)->orderBy('nombre')->get();

        return view('profesores.edit', compact('profesor', 'bloquesParaAsignar', 'sedes'));
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
        $profesor->sincronizarRolesSede($this->filasSedeRolesDesdeRequest($request));
        $profesor->sincronizarRolesUsuario();

        return redirect()->route('profesores.show', $profesor)
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

    /**
     * @return array<int, array{sede_id: int, rol: string}>
     */
    private function filasSedeRolesDesdeRequest(Request $request): array
    {
        if (! Schema::hasTable('profesor_sede')) {
            return [];
        }

        $filas = [];
        foreach ($request->input('sede_roles', []) as $sedeId => $roles) {
            if (! is_array($roles)) {
                continue;
            }
            $sid = (int) $sedeId;
            if ($sid <= 0) {
                continue;
            }
            foreach ($roles as $rol => $on) {
                if (! $on) {
                    continue;
                }
                if (! array_key_exists($rol, Profesor::ROLES_SEDE)) {
                    continue;
                }
                $filas[] = ['sede_id' => $sid, 'rol' => (string) $rol];
            }
        }

        return $filas;
    }
}
