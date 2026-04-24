<?php

namespace App\Http\Controllers;

use App\Models\Cuota;
use App\Models\Bloque;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

class CuotaController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Cuota::with('bloque');
            if ($request->filled('año')) {
                $query->where('año', $request->año);
            }
            if ($request->filled('bloque_id')) {
                $query->where('bloque_id', $request->bloque_id);
            }
            $cuotas = $query->orderBy('año', 'desc')->orderBy('mes')->paginate(20);
            $bloques = Bloque::where('activo', true)->orderBy('nombre')->get();
        } catch (QueryException $e) {
            $cuotas = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
            $bloques = collect();
        }
        return view('cuotas.index', compact('cuotas', 'bloques'));
    }

    public function create()
    {
        try {
            $bloques = Bloque::where('activo', true)->with(['alumnos' => function ($q) {
                $q->orderBy('nombre_apellido');
            }, 'sede'])->orderBy('nombre')->get();
        } catch (QueryException $e) {
            $bloques = collect();
        }
        return view('cuotas.create', compact('bloques'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'bloque_id' => 'required|exists:bloques,id',
            'nombre' => 'required|string|max:255',
            'año' => 'required|integer|min:2020|max:2030',
            'mes' => 'nullable|integer|min:1|max:12',
            'monto' => 'required|numeric|min:0',
            'descripcion' => 'nullable|string|max:500',
            'activo' => 'boolean',
            'alumno_ids' => 'nullable|array',
            'alumno_ids.*' => 'exists:alumnos,id',
        ]);
        $validated['activo'] = $request->has('activo');
        $alumnoIds = $validated['alumno_ids'] ?? [];
        unset($validated['alumno_ids']);
        $cuota = Cuota::create($validated);
        $cuota->alumnos()->sync(is_array($alumnoIds) ? array_filter($alumnoIds) : []);
        return redirect()->route('cuotas.index')->with('success', 'Cuota creada.');
    }

    public function show(Cuota $cuota)
    {
        $cuota->loadCount('pagoDetalles')->load(['bloque', 'alumnos']);
        return view('cuotas.show', compact('cuota'));
    }

    public function edit(Cuota $cuota)
    {
        $bloques = Bloque::where('activo', true)->with(['alumnos' => function ($q) {
            $q->orderBy('nombre_apellido');
        }, 'sede'])->orderBy('nombre')->get();
        $cuota->load('alumnos');
        return view('cuotas.edit', compact('cuota', 'bloques'));
    }

    public function update(Request $request, Cuota $cuota)
    {
        $validated = $request->validate([
            'bloque_id' => 'required|exists:bloques,id',
            'nombre' => 'required|string|max:255',
            'año' => 'required|integer|min:2020|max:2030',
            'mes' => 'nullable|integer|min:1|max:12',
            'monto' => 'required|numeric|min:0',
            'descripcion' => 'nullable|string|max:500',
            'activo' => 'boolean',
            'alumno_ids' => 'nullable|array',
            'alumno_ids.*' => 'exists:alumnos,id',
        ]);
        $validated['activo'] = $request->has('activo');
        $alumnoIds = $validated['alumno_ids'] ?? [];
        unset($validated['alumno_ids']);
        $cuota->update($validated);
        $cuota->alumnos()->sync(is_array($alumnoIds) ? array_filter($alumnoIds) : []);
        return redirect()->route('cuotas.index')->with('success', 'Cuota actualizada.');
    }

    public function destroy(Cuota $cuota)
    {
        $cuota->delete();
        return redirect()->route('cuotas.index')->with('success', 'Cuota eliminada.');
    }
}
