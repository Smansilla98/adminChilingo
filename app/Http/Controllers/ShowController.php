<?php

namespace App\Http\Controllers;

use App\Domain\Agenda\ShowService;
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

    public function store(Request $request, ShowService $shows)
    {
        $validated = $request->validate($shows->reglas());
        $validated['convocatoria_abierta'] = $request->boolean('convocatoria_abierta');
        $shows->guardar(null, $validated, $request->user());

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

    public function update(Request $request, Show $show, ShowService $shows)
    {
        $this->authorize('update', $show);
        $validated = $request->validate($shows->reglas());
        $validated['convocatoria_abierta'] = $request->boolean('convocatoria_abierta');
        $shows->guardar($show, $validated, $request->user());

        return redirect()->route('shows.index')->with('success', 'Show actualizado.');
    }

    public function destroy(Show $show, ShowService $shows)
    {
        $this->authorize('delete', $show);
        $shows->eliminar($show);

        return redirect()->route('shows.index')->with('success', 'Show eliminado.');
    }
}
