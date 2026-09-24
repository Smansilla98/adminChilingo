<?php

namespace App\Http\Controllers;

use App\Models\Bloque;
use App\Models\Show;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class ShowController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Show::with('bloques.sede');
            if ($request->filled('proximos')) {
                $query->proximos();
            } else {
                $query->orderBy('fecha', 'desc');
            }
            $shows = $query->paginate(15);
        } catch (QueryException $e) {
            $shows = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15);
        }

        return view('shows.index', compact('shows'));
    }

    public function create()
    {
        try {
            $bloques = Bloque::where('activo', true)->with('sede', 'profesor')->orderBy('sede_id')->orderBy('nombre')->get();
        } catch (QueryException $e) {
            $bloques = collect();
        }

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
        $this->asegurarBloquesGestionables($validated['bloque_ids'] ?? []);
        $show = Show::create([
            'titulo' => $validated['titulo'],
            'fecha' => $validated['fecha'],
            'hora_inicio' => $validated['hora_inicio'] ? $validated['hora_inicio'].':00' : null,
            'hora_fin' => $validated['hora_fin'] ? $validated['hora_fin'].':00' : null,
            'lugar' => $validated['lugar'] ?? null,
            'descripcion' => $validated['descripcion'] ?? null,
            'convocatoria_abierta' => $validated['convocatoria_abierta'],
        ]);
        if (! empty($validated['bloque_ids'])) {
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
        $this->authorize('update', $show);
        try {
            $show->load('bloques');
            $bloques = Bloque::where('activo', true)->with('sede', 'profesor')->orderBy('sede_id')->orderBy('nombre')->get();
        } catch (QueryException $e) {
            $bloques = collect();
        }

        return view('shows.edit', compact('show', 'bloques'));
    }

    public function update(Request $request, Show $show)
    {
        $this->authorize('update', $show);
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
        $this->asegurarBloquesGestionables($validated['bloque_ids'] ?? []);
        $show->update([
            'titulo' => $validated['titulo'],
            'fecha' => $validated['fecha'],
            'hora_inicio' => $validated['hora_inicio'] ? $validated['hora_inicio'].':00' : null,
            'hora_fin' => $validated['hora_fin'] ? $validated['hora_fin'].':00' : null,
            'lugar' => $validated['lugar'] ?? null,
            'descripcion' => $validated['descripcion'] ?? null,
            'convocatoria_abierta' => $validated['convocatoria_abierta'],
        ]);
        $show->bloques()->sync($validated['bloque_ids'] ?? []);

        return redirect()->route('shows.index')->with('success', 'Show actualizado.');
    }

    public function destroy(Show $show)
    {
        $this->authorize('delete', $show);
        $show->bloques()->detach();
        $show->delete();

        return redirect()->route('shows.index')->with('success', 'Show eliminado.');
    }

    /**
     * Sin alcance global, solo se convoca a bloques propios.
     *
     * @param  list<int|string>  $bloqueIds
     */
    private function asegurarBloquesGestionables(array $bloqueIds): void
    {
        $acceso = auth()->user()->acceso();
        if ($acceso->puedeGlobal('shows.manage')) {
            return;
        }
        if ($bloqueIds === []) {
            abort(403, 'Elegí al menos un bloque de tu alcance.');
        }
        foreach ($bloqueIds as $id) {
            if (! $acceso->puedeEnBloque('shows.manage', (int) $id)) {
                abort(403, 'No podés convocar bloques fuera de tu alcance.');
            }
        }
    }
}
