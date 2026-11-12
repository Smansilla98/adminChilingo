<?php

namespace App\Http\Controllers;

use App\Domain\Pedagogia\ProgresoService;
use App\Models\Alumno;
use App\Models\Bloque;
use App\Models\EvaluacionToque;
use App\Models\ProgresoToque;
use App\Models\PromocionAnio;
use App\Models\Sede;
use Illuminate\Http\Request;

/** Progreso por toque y año (web): grilla del bloque, repasos, paso de año y reporte. */
class ProgresoController extends Controller
{
    public function __construct(private ProgresoService $progreso) {}

    public function index(Request $request)
    {
        $bloques = $this->progreso->bloquesVisibles($request->user())->get(['id', 'nombre', 'año', 'sede_id']);

        return view('progreso.index', ['bloques' => $bloques->groupBy(fn ($b) => $b->sede->nombre ?? 'Sin sede')]);
    }

    public function bloque(Request $request, Bloque $bloque)
    {
        abort_unless($this->progreso->puedeVerBloque($request->user(), $bloque), 403);
        $datos = $this->progreso->matriz($bloque, $request->integer('anio') ?: null);

        return view('progreso.bloque', $datos + [
            'puedeEditar' => $this->progreso->puedeEditarBloque($request->user(), $bloque),
            'estadosPosibles' => ProgresoToque::ESTADOS,
            'resultados' => EvaluacionToque::RESULTADOS,
        ]);
    }

    public function guardar(Request $request, Bloque $bloque)
    {
        $datos = $request->validate(['estados' => 'required|array', 'anio' => 'nullable|integer|min:1|max:7']);
        $cambios = $this->progreso->actualizarGrilla($request->user(), $bloque, $datos['estados']);

        return redirect()->route('progreso.bloque', ['bloque' => $bloque, 'anio' => $datos['anio'] ?? null])
            ->with('success', $cambios === 0 ? 'No había cambios para guardar.' : "Listo: se guardaron {$cambios} cambios.");
    }

    public function evaluacion(Request $request)
    {
        $datos = $request->validate($this->progreso->reglasEvaluacion());
        $this->progreso->registrarEvaluacion($request->user(), $datos + ['visible_alumno' => $request->boolean('visible_alumno')]);

        return back()->with('success', 'Quedó registrado el repaso con su devolución.');
    }

    public function confirmar(Request $request, PromocionAnio $promocion)
    {
        $this->progreso->confirmarPromocion($request->user(), $promocion, $request->input('nota'));

        return back()->with('success', "Listo: pasa a {$promocion->anio_hasta}° año. Quedó en su historial.");
    }

    public function descartar(Request $request, PromocionAnio $promocion)
    {
        $this->progreso->descartarPromocion($request->user(), $promocion, $request->input('nota'));

        return back()->with('success', 'La propuesta quedó descartada. Sigue en el mismo año.');
    }

    public function alumno(Request $request, Alumno $alumno)
    {
        abort_unless($request->user()->acceso()->puedeSobreAlumno('seguimiento.view', $alumno), 403);

        return view('progreso.alumno', [
            'alumno' => $alumno,
            'datos' => $this->progreso->delAlumno($alumno, false),
            'puedeResolver' => $this->progreso->puedeResolver($request->user(), $alumno),
        ]);
    }

    public function reporte(Request $request)
    {
        $sedeId = $request->integer('sede_id') ?: null;
        $filas = collect($this->progreso->reporte($request->user(), $sedeId));
        $sedes = Sede::query()->whereIn('id', $this->progreso->bloquesVisibles($request->user())->pluck('sede_id')->filter()->unique()->all() ?: [0])->orderBy('nombre')->get(['id', 'nombre']);

        return view('progreso.reporte', ['filas' => $filas, 'sedes' => $sedes, 'sedeId' => $sedeId]);
    }
}
