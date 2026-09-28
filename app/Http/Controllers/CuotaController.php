<?php

namespace App\Http\Controllers;

use App\Domain\Datos\EliminacionSegura;
use App\Domain\Finanzas\CuotaService;
use App\Models\Alumno;
use App\Models\Bloque;
use App\Models\Cuota;
use App\Models\Sede;
use App\Models\WhatsappMensaje;
use App\Services\AmbitoSedeService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class CuotaController extends Controller
{
    public function index(Request $request, AmbitoSedeService $ambito)
    {
        $filtroSedes = $ambito->idsPara(auth()->user(), 'cuotas.view');

        try {
            $query = Cuota::with(['bloque', 'sede']);
            if ($filtroSedes !== null) {
                $ambito->aplicarCuotas($query, $filtroSedes);
            }
            if ($request->filled('año')) {
                $query->where('año', $request->año);
            }
            if ($request->filled('bloque_id')) {
                $bid = (int) $request->bloque_id;
                if (Schema::hasColumn('cuotas', 'alcance')) {
                    $bloqueFiltro = Bloque::query()->find($bid);
                    $sedeDelBloque = $bloqueFiltro?->sede_id;
                    $query->where(function ($q) use ($bid, $sedeDelBloque) {
                        $q->where('bloque_id', $bid)
                            ->orWhere('alcance', Cuota::ALCANCE_GENERAL);
                        if ($sedeDelBloque) {
                            $q->orWhere(function ($q2) use ($sedeDelBloque) {
                                $q2->where('alcance', Cuota::ALCANCE_SEDE)
                                    ->where('sede_id', $sedeDelBloque);
                            });
                        }
                    });
                } else {
                    $query->where('bloque_id', $bid);
                }
            }
            if ($request->filled('sede_id') && \Illuminate\Support\Facades\Schema::hasColumn('cuotas', 'sede_id')) {
                $query->where('sede_id', $request->sede_id);
            }
            if ($request->filled('alcance') && \Illuminate\Support\Facades\Schema::hasColumn('cuotas', 'alcance')) {
                $query->where('alcance', $request->alcance);
            }
            $cuotas = $query->orderBy('año', 'desc')->orderBy('mes')->paginate(20);
            $bloquesQ = Bloque::where('activo', true)->orderBy('nombre');
            $sedesQ = Sede::where('activo', true)->orderBy('nombre');
            if ($filtroSedes !== null) {
                $ambito->aplicarBloques($bloquesQ, $filtroSedes);
                $ambito->aplicarSedesCatalogo($sedesQ, $filtroSedes);
            }
            $bloques = $bloquesQ->get();
            $sedes = $sedesQ->get();
        } catch (QueryException $e) {
            $cuotas = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
            $bloques = collect();
            $sedes = collect();
        }

        return view('cuotas.index', compact('cuotas', 'bloques', 'sedes'));
    }

    public function create(AmbitoSedeService $ambito)
    {
        $filtroSedes = $ambito->idsPara(auth()->user(), 'cuotas.view');
        try {
            $bloquesQ = Bloque::where('activo', true)->with(['alumnos' => function ($q) {
                $q->orderBy('nombre_apellido');
            }, 'sede'])->orderBy('nombre');
            if ($filtroSedes !== null) {
                $ambito->aplicarBloques($bloquesQ, $filtroSedes);
            }
            $bloques = $bloquesQ->get();
        } catch (QueryException $e) {
            $bloques = collect();
        }
        try {
            $sedesQ = Sede::where('activo', true)->orderBy('nombre');
            if ($filtroSedes !== null) {
                $ambito->aplicarSedesCatalogo($sedesQ, $filtroSedes);
            }
            $sedes = $sedesQ->get();
        } catch (QueryException $e) {
            $sedes = collect();
        }
        try {
            $alumnosQ = Alumno::where('activo', true)->orderBy('nombre_apellido');
            if ($filtroSedes !== null) {
                $ambito->aplicarAlumnos($alumnosQ, $filtroSedes);
            }
            $alumnosActivos = $alumnosQ->get(['id', 'nombre_apellido', 'sede_id']);
        } catch (QueryException $e) {
            $alumnosActivos = collect();
        }

        return view('cuotas.create', compact('bloques', 'sedes', 'alumnosActivos'));
    }

    public function store(Request $request, CuotaService $cuotas)
    {
        $validated = $request->validate($cuotas->reglas($request->input('alcance')));
        $validated['activo'] = $request->has('activo');
        $cuotas->guardar(null, $validated, $request->user());

        return redirect()->route('cuotas.index')->with('success', 'Cuota creada.');
    }

    public function show(Cuota $cuota)
    {
        $this->authorize('view', $cuota);
        $cuota->loadCount('pagoDetalles')->load(['bloque', 'sede', 'alumnos']);

        $recordatoriosWhatsapp = collect();
        if (Schema::hasTable('whatsapp_mensajes')) {
            $recordatoriosWhatsapp = WhatsappMensaje::query()
                ->where('cuota_id', $cuota->id)
                ->where('tipo', WhatsappMensaje::TIPO_CUOTA)
                ->with('alumno')
                ->orderByDesc('id')
                ->get()
                ->unique('alumno_id')
                ->values();
        }

        return view('cuotas.show', compact('cuota', 'recordatoriosWhatsapp'));
    }

    public function edit(Cuota $cuota)
    {
        $this->authorize('update', $cuota);
        try {
            $bloques = Bloque::where('activo', true)->with(['alumnos' => function ($q) {
                $q->orderBy('nombre_apellido');
            }, 'sede'])->orderBy('nombre')->get();
        } catch (QueryException $e) {
            $bloques = collect();
        }
        try {
            $sedes = Sede::where('activo', true)->orderBy('nombre')->get();
        } catch (QueryException $e) {
            $sedes = collect();
        }
        try {
            $alumnosActivos = Alumno::where('activo', true)->orderBy('nombre_apellido')->get(['id', 'nombre_apellido', 'sede_id']);
        } catch (QueryException $e) {
            $alumnosActivos = collect();
        }
        $cuota->load('alumnos');

        return view('cuotas.edit', compact('cuota', 'bloques', 'sedes', 'alumnosActivos'));
    }

    public function update(Request $request, Cuota $cuota, CuotaService $cuotas)
    {
        $this->authorize('update', $cuota);
        $validated = $request->validate($cuotas->reglas($request->input('alcance')));
        $validated['activo'] = $request->has('activo');
        $cuotas->guardar($cuota, $validated, $request->user());

        return redirect()->route('cuotas.index')->with('success', 'Cuota actualizada.');
    }

    public function destroy(Cuota $cuota, EliminacionSegura $eliminacion)
    {
        $this->authorize('delete', $cuota);
        $eliminacion->verificar($cuota);
        $cuota->delete();

        return redirect()->route('cuotas.index')->with('success', 'Cuota eliminada.');
    }
}
