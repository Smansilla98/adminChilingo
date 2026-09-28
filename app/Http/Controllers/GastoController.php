<?php

namespace App\Http\Controllers;

use App\Domain\Finanzas\GastoService;
use App\Models\Bloque;
use App\Models\Gasto;
use App\Models\Sede;
use App\Services\AmbitoSedeService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class GastoController extends Controller
{
    public function index(Request $request, AmbitoSedeService $ambito)
    {
        $gastos = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 25);
        $sedes = collect();
        $filtroSedes = $ambito->idsPara(auth()->user(), 'gastos.view');

        if (Schema::hasTable('gastos')) {
            try {
                $query = Gasto::with(['sede', 'bloque', 'creador'])->orderByDesc('fecha')->orderByDesc('id');
                if ($filtroSedes !== null) {
                    $ambito->aplicarGastos($query, $filtroSedes);
                }

                if ($request->filled('sede_id')) {
                    $query->where('sede_id', $request->sede_id);
                }
                if ($request->filled('tipo')) {
                    $query->where('tipo', $request->tipo);
                }
                if ($request->filled('desde')) {
                    $query->where('fecha', '>=', $request->desde);
                }
                if ($request->filled('hasta')) {
                    $query->where('fecha', '<=', $request->hasta);
                }

                $gastos = $query->paginate(25);
            } catch (QueryException $e) {
                // mantener paginador vacío
            }
        }
        if (Schema::hasTable('sedes')) {
            try {
                $sedesQ = Sede::orderBy('nombre');
                if ($filtroSedes !== null) {
                    $ambito->aplicarSedesCatalogo($sedesQ, $filtroSedes);
                }
                $sedes = $sedesQ->get();
            } catch (QueryException $e) {
                // mantener collect()
            }
        }

        return view('gastos.index', compact('gastos', 'sedes'));
    }

    public function create(Request $request, AmbitoSedeService $ambito)
    {
        $sedes = collect();
        $bloques = collect();
        $filtroSedes = $ambito->idsPara(auth()->user(), 'gastos.view');
        if (Schema::hasTable('sedes')) {
            try {
                $sedesQ = Sede::orderBy('nombre');
                if ($filtroSedes !== null) {
                    $ambito->aplicarSedesCatalogo($sedesQ, $filtroSedes);
                }
                $sedes = $sedesQ->get();
            } catch (QueryException $e) {
                // mantener collect()
            }
        }
        if (Schema::hasTable('bloques')) {
            try {
                $bloquesQ = Bloque::where('activo', true)->orderBy('sede_id')->orderBy('nombre');
                if ($filtroSedes !== null) {
                    $ambito->aplicarBloques($bloquesQ, $filtroSedes);
                }
                $bloques = $bloquesQ->get();
            } catch (QueryException $e) {
                // mantener collect()
            }
        }

        return view('gastos.create', compact('sedes', 'bloques'));
    }

    public function store(Request $request, GastoService $gastos)
    {
        $gasto = $gastos->registrar($request->validate($gastos->reglas()), $request->user());

        return redirect()->route('gastos.index')->with('success', $gasto->estado === 'aprobado' ? 'Gasto registrado.' : 'Gasto registrado. Queda pendiente de aprobación.');
    }

    public function show(Gasto $gasto)
    {
        $this->authorize('view', $gasto);
        $gasto->load(['sede', 'bloque', 'creador']);

        return view('gastos.show', compact('gasto'));
    }

    public function edit(Gasto $gasto)
    {
        $this->authorize('update', $gasto);
        $sedes = collect();
        $bloques = collect();
        if (Schema::hasTable('sedes')) {
            try {
                $sedes = Sede::orderBy('nombre')->get();
            } catch (QueryException $e) {
                // mantener collect()
            }
        }
        if (Schema::hasTable('bloques')) {
            try {
                $bloques = Bloque::where('activo', true)->orderBy('sede_id')->orderBy('nombre')->get();
            } catch (QueryException $e) {
                // mantener collect()
            }
        }

        return view('gastos.edit', compact('gasto', 'sedes', 'bloques'));
    }

    public function update(Request $request, Gasto $gasto, GastoService $gastos)
    {
        $this->authorize('update', $gasto);
        $gastos->actualizar($gasto, $request->validate($gastos->reglas()), $request->user());

        return redirect()->route('gastos.index')->with('success', 'Gasto actualizado.');
    }

    public function destroy(Gasto $gasto)
    {
        $this->authorize('delete', $gasto);
        $gasto->delete();

        return redirect()->route('gastos.index')->with('success', 'Gasto eliminado.');
    }

    public function aprobar(Request $request, Gasto $gasto, GastoService $gastos)
    {
        $this->authorize('approve', $gasto);
        $data = $request->validate(['decision' => 'required|in:aprobado,rechazado']);
        $gastos->decidir($gasto, $data['decision'], $request->user());

        return back()->with('success', $data['decision'] === 'aprobado' ? 'Gasto aprobado.' : 'Gasto rechazado.');
    }
}
