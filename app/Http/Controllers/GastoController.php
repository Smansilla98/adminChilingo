<?php

namespace App\Http\Controllers;

use App\Domain\Finanzas\GastoService;
use App\Domain\Finanzas\Papelera;
use App\Models\Bloque;
use App\Models\Gasto;
use App\Models\Sede;
use App\Services\AmbitoSedeService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class GastoController extends Controller
{
    public function index(Request $request, AmbitoSedeService $ambito)
    {
        $gastos = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 25);
        $sedes = collect();
        $filtroSedes = $ambito->idsPara(auth()->user(), 'gastos.view');

        if (Schema::hasTable('gastos')) {
            try {
                $query = Gasto::with(['sede', 'bloque', 'creador', 'eliminadoPor'])->orderByDesc('fecha')->orderByDesc('id');
                if ($request->boolean('papelera')) {
                    $query->onlyTrashed();
                }
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
        $datos = $request->validate($gastos->reglas() + $this->reglaComprobante());
        $gasto = $gastos->registrar(array_diff_key($datos, ['comprobante' => 1]), $request->user());
        if ($request->hasFile('comprobante')) {
            $gastos->adjuntarComprobante($gasto, $request->file('comprobante'));
        }

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
        $datos = $request->validate($gastos->reglas() + $this->reglaComprobante());
        $gastos->actualizar($gasto, array_diff_key($datos, ['comprobante' => 1]), $request->user());
        if ($request->hasFile('comprobante')) {
            $gastos->adjuntarComprobante($gasto, $request->file('comprobante'));
        }

        return redirect()->route('gastos.index')->with('success', 'Gasto actualizado.');
    }

    public function destroy(Request $request, Gasto $gasto, Papelera $papelera)
    {
        $this->authorize('delete', $gasto);
        $motivo = $request->validate(['motivo' => 'nullable|string|max:500'])['motivo'] ?? null;
        $papelera->eliminar($gasto, $request->user(), $motivo);

        return redirect()->route('gastos.index')->with('success', 'Gasto enviado a la papelera. Lo podés restaurar desde ahí.');
    }

    public function restaurar(Gasto $gasto, Papelera $papelera)
    {
        $this->authorize('delete', $gasto);
        $papelera->restaurar($gasto);

        return redirect()->route('gastos.index')->with('success', 'Gasto restaurado.');
    }

    /** Comprobante adjunto del gasto (factura o ticket). */
    public function comprobante(Gasto $gasto)
    {
        $this->authorize('view', $gasto);
        abort_if($gasto->comprobante_path === null, 404);
        abort_unless(Storage::disk('comprobantes')->exists($gasto->comprobante_path), 404, 'El archivo ya no está disponible.');
        $ext = strtolower((string) pathinfo($gasto->comprobante_path, PATHINFO_EXTENSION)) ?: 'pdf';

        return Storage::disk('comprobantes')->response($gasto->comprobante_path, 'comprobante-gasto-'.$gasto->id.'.'.$ext);
    }

    /** @return array<string, mixed> */
    private function reglaComprobante(): array
    {
        return ['comprobante' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240'];
    }

    public function aprobar(Request $request, Gasto $gasto, GastoService $gastos)
    {
        $this->authorize('approve', $gasto);
        $data = $request->validate(['decision' => 'required|in:aprobado,rechazado']);
        $gastos->decidir($gasto, $data['decision'], $request->user());

        return back()->with('success', $data['decision'] === 'aprobado' ? 'Gasto aprobado.' : 'Gasto rechazado.');
    }
}
