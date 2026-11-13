<?php

namespace App\Http\Controllers;

use App\Domain\Docentes\LiquidacionService;
use App\Models\AjusteHorasDocente;
use App\Models\DocenteTarifa;
use App\Models\LiquidacionDocenteMensual;
use App\Models\Profesor;
use App\Models\Sede;
use Illuminate\Http\Request;

/** Liquidación docente por horas (web): período, detalle, valor hora, ajustes, estados y recibo. */
class LiquidacionDocenteController extends Controller
{
    public function __construct(private LiquidacionService $liquidaciones) {}

    /** @return array{0: int, 1: int} */
    private function periodo(Request $request): array
    {
        $anio = min((int) now()->year + 1, max(2020, $request->integer('anio') ?: (int) now()->year));
        $mes = min(12, max(1, $request->integer('mes') ?: (int) now()->month));

        return [$anio, $mes];
    }

    public function index(Request $request)
    {
        [$anio, $mes] = $this->periodo($request);
        $profesores = $this->liquidaciones->profesoresVisibles($request->user());
        $liqs = LiquidacionDocenteMensual::query()->where('anio', $anio)->where('mes', $mes)->whereIn('profesor_id', $profesores->pluck('id')->all() ?: [0])->get()->keyBy('profesor_id');
        $modalidades = DocenteTarifa::query()->whereIn('profesor_id', $profesores->pluck('id')->all() ?: [0])->whereNull('sede_id')->pluck('modalidad', 'profesor_id');

        return view('liquidaciones.index', [
            'anio' => $anio, 'mes' => $mes, 'profesores' => $profesores, 'liqs' => $liqs, 'modalidades' => $modalidades,
            'puedeGestionar' => $request->user()->acceso()->puede('liquidaciones.manage'),
        ]);
    }

    public function generar(Request $request)
    {
        [$anio, $mes] = $this->periodo($request);
        if ($request->filled('profesor_id')) {
            $liq = $this->liquidaciones->generar($request->user(), Profesor::query()->findOrFail($request->integer('profesor_id')), $anio, $mes);

            return redirect()->route('liquidaciones.show', $liq)->with('success', 'Liquidación calculada. Revisala antes de aprobarla.');
        }
        $n = $this->liquidaciones->generarTodas($request->user(), $anio, $mes);

        return redirect()->route('liquidaciones.index', ['anio' => $anio, 'mes' => $mes])->with('success', "Se calcularon {$n} liquidaciones en preparación.");
    }

    public function show(Request $request, LiquidacionDocenteMensual $liquidacion)
    {
        abort_unless($this->liquidaciones->puedeVer($request->user(), $liquidacion), 403);
        $profesor = $liquidacion->profesor()->firstOrFail();

        return view('liquidaciones.show', [
            'l' => $liquidacion,
            'profesor' => $profesor,
            'puedeGestionar' => $this->liquidaciones->puede($request->user(), 'liquidaciones.manage', $profesor),
            'tiposAjuste' => AjusteHorasDocente::TIPOS,
            'bloques' => app(\App\Domain\Docentes\HorasDictadasService::class)->bloquesQueDicta($profesor),
        ]);
    }

    public function aprobar(Request $request, LiquidacionDocenteMensual $liquidacion)
    {
        $this->liquidaciones->aprobar($request->user(), $liquidacion);

        return back()->with('success', 'Liquidación aprobada. El docente ya la ve en la app.');
    }

    public function reabrir(Request $request, LiquidacionDocenteMensual $liquidacion)
    {
        $this->liquidaciones->volverABorrador($request->user(), $liquidacion);

        return back()->with('success', 'La liquidación volvió a preparación.');
    }

    public function pagar(Request $request, LiquidacionDocenteMensual $liquidacion)
    {
        $r = $this->liquidaciones->pagar($request->user(), $liquidacion, $request->boolean('registrar_gasto', true));
        $msg = 'Liquidación marcada como pagada.';
        if ($r['gastos'] !== []) {
            $msg .= ' Se registró el gasto de sueldo.';
        }
        if ($r['sin_gasto'] !== []) {
            $msg .= ' No se registró el gasto en: '.implode(', ', $r['sin_gasto']).' (falta permiso de gastos en esa sede).';
        }

        return back()->with('success', $msg);
    }

    public function recibo(Request $request, LiquidacionDocenteMensual $liquidacion)
    {
        abort_unless($this->liquidaciones->puedeVer($request->user(), $liquidacion), 403);

        return view('liquidaciones.recibo', ['l' => $liquidacion->load('profesor', 'aprobadaPor', 'pagadaPor'), 'imprimir' => true]);
    }

    /** Recibo en PDF (descarga). */
    public function reciboPdf(Request $request, LiquidacionDocenteMensual $liquidacion)
    {
        abort_unless($this->liquidaciones->puedeVer($request->user(), $liquidacion), 403);

        return response(\App\Domain\Docentes\ReciboLiquidacionPdf::pdf($liquidacion), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.\App\Domain\Docentes\ReciboLiquidacionPdf::nombreArchivo($liquidacion).'"',
        ]);
    }

    public function tarifa(Request $request, Profesor $profesor)
    {
        abort_unless($this->liquidaciones->puede($request->user(), 'liquidaciones.manage', $profesor), 403);
        $sedes = Sede::query()->whereIn('id', $this->liquidaciones->sedesDelProfesor($profesor) ?: [0])->orderBy('nombre')->get(['id', 'nombre']);

        return view('liquidaciones.tarifa', [
            'profesor' => $profesor,
            'sedes' => $sedes,
            'modalidad' => $this->liquidaciones->modalidad($profesor),
            'valores' => $this->liquidaciones->valoresHora($profesor),
            'modalidades' => DocenteTarifa::MODALIDADES,
        ]);
    }

    public function guardarTarifa(Request $request, Profesor $profesor)
    {
        $this->liquidaciones->guardarTarifa($request->user(), $profesor, $request->validate($this->liquidaciones->reglasTarifa()));

        return redirect()->route('liquidaciones.index')->with('success', 'Guardamos la modalidad y el valor hora de '.$profesor->nombre.'.');
    }

    public function ajuste(Request $request)
    {
        $this->liquidaciones->agregarAjuste($request->user(), $request->validate($this->liquidaciones->reglasAjuste()));

        return back()->with('success', 'Ajuste cargado. Recalculá la liquidación para verlo en el total.');
    }

    public function eliminarAjuste(Request $request, AjusteHorasDocente $ajuste)
    {
        $this->liquidaciones->eliminarAjuste($request->user(), $ajuste);

        return back()->with('success', 'Ajuste eliminado. Recalculá la liquidación.');
    }

    /** El docente: sus liquidaciones aprobadas y pagadas. */
    public function mias(Request $request)
    {
        $profesor = $this->liquidaciones->profesorDe($request->user());
        abort_unless($profesor !== null, 403, 'Solo para docentes.');

        return view('liquidaciones.mias', ['liqs' => $this->liquidaciones->delDocente($profesor)]);
    }
}
