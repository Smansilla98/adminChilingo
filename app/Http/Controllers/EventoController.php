<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\Profesor;
use App\Models\Bloque;
use App\Models\Sede;
use Illuminate\Http\Request;

class EventoController extends Controller
{
    public function index(Request $request)
    {
        $query = Evento::with(['sede', 'profesor', 'bloque', 'creador']);

        if ($request->filled('sede_id')) {
            $query->where('sede_id', $request->sede_id);
        }

        if ($request->filled('profesor_id')) {
            $query->where('profesor_id', $request->profesor_id);
        }

        if ($request->filled('tipo_evento')) {
            $query->where('tipo_evento', $request->tipo_evento);
        }

        $eventos = $query->orderBy('fecha', 'desc')->paginate(20);
        $sedes = Sede::where('activo', true)->get();
        $profesores = Profesor::where('activo', true)->get();
        $tiposEvento = ['show', 'taller', 'muestra', 'muestra_alumnos', 'caminata_1er', 'show_beneficio', 'gira', 'villa_gesell', 'aniversario', 'fiesta', 'rifa', 'otro'];

        return view('eventos.index', compact('eventos', 'sedes', 'profesores', 'tiposEvento'));
    }

    public function create()
    {
        $sedes = Sede::where('activo', true)->get();
        $profesores = Profesor::where('activo', true)->get();
        $bloques = Bloque::where('activo', true)->with('sede')->get();
        $tiposEvento = ['show', 'taller', 'muestra', 'muestra_alumnos', 'caminata_1er', 'show_beneficio', 'gira', 'villa_gesell', 'aniversario', 'fiesta', 'rifa', 'otro'];

        return view('eventos.create', compact('sedes', 'profesores', 'bloques', 'tiposEvento'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'fecha' => 'required|date',
            'hora_inicio' => 'nullable|date_format:H:i',
            'hora_fin' => 'nullable|date_format:H:i|after:hora_inicio',
            'sede_id' => 'nullable|exists:sedes,id',
            'tipo_evento' => 'required|in:show,taller,muestra,muestra_alumnos,caminata_1er,show_beneficio,gira,villa_gesell,aniversario,fiesta,rifa,otro',
            'profesor_id' => 'nullable|exists:profesores,id',
            'bloque_id' => 'nullable|exists:bloques,id',
            'cantidad_personas' => 'nullable|integer|min:0',
        ]);

        $validated['created_by'] = auth()->id();

        Evento::create($validated);

        return redirect()->route('eventos.index')
            ->with('success', 'Evento creado exitosamente.');
    }

    public function show(Evento $evento)
    {
        $evento->load(['sede', 'profesor', 'bloque', 'creador']);
        return view('eventos.show', compact('evento'));
    }

    public function edit(Evento $evento)
    {
        $sedes = Sede::where('activo', true)->get();
        $profesores = Profesor::where('activo', true)->get();
        $bloques = Bloque::where('activo', true)->with('sede')->get();
        $tiposEvento = ['show', 'taller', 'muestra', 'muestra_alumnos', 'caminata_1er', 'show_beneficio', 'gira', 'villa_gesell', 'aniversario', 'fiesta', 'rifa', 'otro'];

        return view('eventos.edit', compact('evento', 'sedes', 'profesores', 'bloques', 'tiposEvento'));
    }

    public function update(Request $request, Evento $evento)
    {
        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'fecha' => 'required|date',
            'hora_inicio' => 'nullable|date_format:H:i',
            'hora_fin' => 'nullable|date_format:H:i|after:hora_inicio',
            'sede_id' => 'nullable|exists:sedes,id',
            'tipo_evento' => 'required|in:show,taller,muestra,muestra_alumnos,caminata_1er,show_beneficio,gira,villa_gesell,aniversario,fiesta,rifa,otro',
            'profesor_id' => 'nullable|exists:profesores,id',
            'bloque_id' => 'nullable|exists:bloques,id',
            'cantidad_personas' => 'nullable|integer|min:0',
        ]);

        $evento->update($validated);

        return redirect()->route('eventos.index')
            ->with('success', 'Evento actualizado exitosamente.');
    }

    public function destroy(Evento $evento)
    {
        $evento->delete();

        return redirect()->route('eventos.index')
            ->with('success', 'Evento eliminado exitosamente.');
    }
}
