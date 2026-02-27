<?php

namespace App\Http\Controllers;

use App\Models\Show;
use App\Models\Bloque;
use Illuminate\Http\Request;

class ShowController extends Controller
{
    public function index(Request $request)
    {
        $query = Show::with('bloques.sede');
        if ($request->filled('proximos')) {
            $query->proximos();
        } else {
            $query->orderBy('fecha', 'desc');
        }
        $shows = $query->paginate(15);
        return view('shows.index', compact('shows'));
    }

    public function create()
    {
        $bloques = Bloque::where('activo', true)->with('sede', 'profesor')->orderBy('sede_id')->orderBy('nombre')->get();
        return view('shows.create', compact('bloques'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'fecha' => 'required|date',
            'hora_inicio' => 'nullable|date_format:H:i',
            'hora_fin' => 'nullable|date_format:H:i|after_or_equal:hora_inicio',
            'lugar' => 'nullable|string|max:255',
            'descripcion' => 'nullable|string',
            'convocatoria_abierta' => 'boolean',
            'bloque_ids' => 'nullable|array',
            'bloque_ids.*' => 'exists:bloques,id',
        ]);
        $validated['convocatoria_abierta'] = $request->boolean('convocatoria_abierta');
        $show = Show::create([
            'titulo' => $validated['titulo'],
            'fecha' => $validated['fecha'],
            'hora_inicio' => $validated['hora_inicio'] ? $validated['hora_inicio'] . ':00' : null,
            'hora_fin' => $validated['hora_fin'] ? $validated['hora_fin'] . ':00' : null,
            'lugar' => $validated['lugar'] ?? null,
            'descripcion' => $validated['descripcion'] ?? null,
            'convocatoria_abierta' => $validated['convocatoria_abierta'],
        ]);
        if (!empty($validated['bloque_ids'])) {
            $show->bloques()->sync($validated['bloque_ids']);
        }
        return redirect()->route('shows.index')->with('success', 'Show creado.');
    }

    public function show(Show $show)
    {
        $show->load('bloques.sede', 'bloques.profesor');
        return view('shows.show', compact('show'));
    }

    public function edit(Show $show)
    {
        $show->load('bloques');
        $bloques = Bloque::where('activo', true)->with('sede', 'profesor')->orderBy('sede_id')->orderBy('nombre')->get();
        return view('shows.edit', compact('show', 'bloques'));
    }

    public function update(Request $request, Show $show)
    {
        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'fecha' => 'required|date',
            'hora_inicio' => 'nullable|date_format:H:i',
            'hora_fin' => 'nullable|date_format:H:i|after_or_equal:hora_inicio',
            'lugar' => 'nullable|string|max:255',
            'descripcion' => 'nullable|string',
            'convocatoria_abierta' => 'boolean',
            'bloque_ids' => 'nullable|array',
            'bloque_ids.*' => 'exists:bloques,id',
        ]);
        $validated['convocatoria_abierta'] = $request->boolean('convocatoria_abierta');
        $show->update([
            'titulo' => $validated['titulo'],
            'fecha' => $validated['fecha'],
            'hora_inicio' => $validated['hora_inicio'] ? $validated['hora_inicio'] . ':00' : null,
            'hora_fin' => $validated['hora_fin'] ? $validated['hora_fin'] . ':00' : null,
            'lugar' => $validated['lugar'] ?? null,
            'descripcion' => $validated['descripcion'] ?? null,
            'convocatoria_abierta' => $validated['convocatoria_abierta'],
        ]);
        $show->bloques()->sync($validated['bloque_ids'] ?? []);
        return redirect()->route('shows.index')->with('success', 'Show actualizado.');
    }

    public function destroy(Show $show)
    {
        $show->bloques()->detach();
        $show->delete();
        return redirect()->route('shows.index')->with('success', 'Show eliminado.');
    }
}
