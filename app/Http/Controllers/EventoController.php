<?php

namespace App\Http\Controllers;

use App\Domain\Agenda\EventoService;
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
        $tiposEvento = array_keys(EventoService::TIPOS);

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
        $tiposEvento = array_keys(EventoService::TIPOS);

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

    public function store(Request $request, EventoService $eventos)
    {
        $this->authorize('create', Evento::class);
        $eventos->crear($request->validate($eventos->reglas()), $request->user());

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
        $tiposEvento = array_keys(EventoService::TIPOS);

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

    public function update(Request $request, Evento $evento, EventoService $eventos)
    {
        $this->authorize('update', $evento);
        $eventos->actualizar($evento, $request->validate($eventos->reglas()), $request->user());

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
}
