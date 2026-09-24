<?php

namespace App\Http\Controllers;

use App\Models\Bloque;
use App\Models\Evento;
use App\Models\Profesor;
use App\Models\Sede;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class EventoController extends Controller
{
    public function index(Request $request)
    {
        $tiposEvento = ['show', 'taller', 'muestra', 'muestra_alumnos', 'caminata_1er', 'show_beneficio', 'gira', 'villa_gesell', 'aniversario', 'fiesta', 'rifa', 'otro'];

        try {
            /** @var \App\Models\User|null $user */
            $user = auth()->user();
            $query = Evento::with(['sede', 'profesor', 'bloque', 'creador']);
            $user->acceso()->alcance('eventos.view')->aplicarEventos($query);

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
        } catch (QueryException $e) {
            $eventos = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
            $sedes = collect();
            $profesores = collect();
        }

        return view('eventos.index', compact('eventos', 'sedes', 'profesores', 'tiposEvento'));
    }

    public function create()
    {
        $this->authorize('create', Evento::class);
        $tiposEvento = ['show', 'taller', 'muestra', 'muestra_alumnos', 'caminata_1er', 'show_beneficio', 'gira', 'villa_gesell', 'aniversario', 'fiesta', 'rifa', 'otro'];

        try {
            $sedes = Sede::where('activo', true)->get();
            $profesores = Profesor::where('activo', true)->get();
            $bloques = Bloque::where('activo', true)->with('sede')->get();
        } catch (QueryException $e) {
            $sedes = collect();
            $profesores = collect();
            $bloques = collect();
        }

        return view('eventos.create', compact('sedes', 'profesores', 'bloques', 'tiposEvento'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Evento::class);
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
        $this->asegurarAmbitoEvento('eventos.create', $validated);

        Evento::create($validated);

        return redirect()->route('eventos.index')
            ->with('success', 'Evento creado exitosamente.');
    }

    public function show(Evento $evento)
    {
        $this->authorize('view', $evento);
        $evento->load(['sede', 'profesor', 'bloque', 'creador']);

        return view('eventos.show', compact('evento'));
    }

    public function edit(Evento $evento)
    {
        $this->authorize('update', $evento);
        $tiposEvento = ['show', 'taller', 'muestra', 'muestra_alumnos', 'caminata_1er', 'show_beneficio', 'gira', 'villa_gesell', 'aniversario', 'fiesta', 'rifa', 'otro'];

        try {
            $sedes = Sede::where('activo', true)->get();
            $profesores = Profesor::where('activo', true)->get();
            $bloques = Bloque::where('activo', true)->with('sede')->get();
        } catch (QueryException $e) {
            $sedes = collect();
            $profesores = collect();
            $bloques = collect();
        }

        return view('eventos.edit', compact('evento', 'sedes', 'profesores', 'bloques', 'tiposEvento'));
    }

    public function update(Request $request, Evento $evento)
    {
        $this->authorize('update', $evento);
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

        $this->asegurarAmbitoEvento('eventos.update', $validated);
        $evento->update($validated);

        return redirect()->route('eventos.index')
            ->with('success', 'Evento actualizado exitosamente.');
    }

    public function destroy(Evento $evento)
    {
        $this->authorize('delete', $evento);
        $evento->delete();

        return redirect()->route('eventos.index')
            ->with('success', 'Evento eliminado exitosamente.');
    }

    /**
     * El evento resultante (bloque / sede / toda la escuela) tiene que quedar dentro del alcance.
     *
     * @param  array<string, mixed>  $datos
     */
    private function asegurarAmbitoEvento(string $permiso, array $datos): void
    {
        $acceso = auth()->user()->acceso();
        $ok = match (true) {
            ! empty($datos['bloque_id']) => $acceso->puedeEnBloque($permiso, (int) $datos['bloque_id']),
            ! empty($datos['sede_id']) => $acceso->puedeEnSede($permiso, (int) $datos['sede_id']),
            default => $acceso->puedeGlobal($permiso),
        };
        if (! $ok) {
            abort(403, 'No podés crear o mover eventos a ese ámbito.');
        }
    }
}
