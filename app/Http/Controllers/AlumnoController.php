<?php

namespace App\Http\Controllers;

use App\Models\Alumno;
use App\Models\Bloque;
use App\Models\Sede;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\AlumnosExport;

class AlumnoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Alumno::with(['bloque', 'bloques', 'sede']);

        // Filtros
        if ($request->filled('sede_id')) {
            $query->where('sede_id', $request->sede_id);
        }

        if ($request->filled('bloque_id')) {
            $query->whereHas('bloques', function ($q) use ($request) {
                $q->where('bloques.id', $request->bloque_id);
            });
        }

        if ($request->filled('activo')) {
            $query->where('activo', $request->activo === '1');
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nombre_apellido', 'like', "%{$search}%")
                  ->orWhere('dni', 'like', "%{$search}%");
            });
        }

        $alumnos = $query->orderBy('nombre_apellido')->paginate(20);
        $sedes = Sede::where('activo', true)->get();
        $bloques = Bloque::where('activo', true)->with('sede')->get();

        return view('alumnos.index', compact('alumnos', 'sedes', 'bloques'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $sedes = Sede::where('activo', true)->get();
        $bloques = Bloque::where('activo', true)->with('sede')->get();
        $instrumentos = \App\Models\Bloque::TAMBORES_DISPONIBLES;

        return view('alumnos.create', compact('sedes', 'bloques', 'instrumentos'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre_apellido' => 'required|string|max:255',
            'dni' => 'required|string|unique:alumnos,dni|max:20',
            'fecha_nacimiento' => 'required|date',
            'telefono' => 'nullable|string|max:20',
            'instrumento_principal' => 'required|string',
            'instrumento_secundario' => 'nullable|string',
            'tipo_tambor' => 'required|in:Sede,Propio',
            'bloque_id' => 'nullable|exists:bloques,id',
            'sede_id' => 'required|exists:sedes,id',
            'activo' => 'boolean',
        ]);

        $validated['activo'] = $request->has('activo') ? true : true;

        $alumno = Alumno::create($validated);

        if (!empty($validated['bloque_id'])) {
            $alumno->bloques()->attach($validated['bloque_id'], ['es_principal' => true]);
        }

        return redirect()->route('alumnos.index')
            ->with('success', 'Alumno creado exitosamente.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Alumno $alumno)
    {
        $alumno->load(['bloque.profesor', 'sede', 'asistencias']);
        return view('alumnos.show', compact('alumno'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Alumno $alumno)
    {
        $sedes = Sede::where('activo', true)->get();
        $bloques = Bloque::where('activo', true)->with('sede')->get();
        $instrumentos = \App\Models\Bloque::TAMBORES_DISPONIBLES;

        return view('alumnos.edit', compact('alumno', 'sedes', 'bloques', 'instrumentos'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Alumno $alumno)
    {
        $validated = $request->validate([
            'nombre_apellido' => 'required|string|max:255',
            'dni' => 'required|string|unique:alumnos,dni,' . $alumno->id . '|max:20',
            'fecha_nacimiento' => 'required|date',
            'telefono' => 'nullable|string|max:20',
            'instrumento_principal' => 'required|string',
            'instrumento_secundario' => 'nullable|string',
            'tipo_tambor' => 'required|in:Sede,Propio',
            'bloque_id' => 'nullable|exists:bloques,id',
            'sede_id' => 'required|exists:sedes,id',
            'activo' => 'boolean',
        ]);

        $validated['activo'] = $request->has('activo') ? true : ($request->has('activo') ? false : $alumno->activo);

        $alumno->update($validated);

        if (array_key_exists('bloque_id', $validated)) {
            $alumno->bloques()->sync(
                $validated['bloque_id']
                    ? [$validated['bloque_id'] => ['es_principal' => true]]
                    : []
            );
        }

        return redirect()->route('alumnos.index')
            ->with('success', 'Alumno actualizado exitosamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Alumno $alumno)
    {
        $alumno->delete();

        return redirect()->route('alumnos.index')
            ->with('success', 'Alumno eliminado exitosamente.');
    }

    /**
     * Exportar alumnos a Excel
     */
    public function export(Request $request)
    {
        return Excel::download(new AlumnosExport($request), 'alumnos_' . now()->format('Y-m-d') . '.xlsx');
    }
}
