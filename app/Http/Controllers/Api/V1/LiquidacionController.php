<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Docentes\LiquidacionService;
use App\Http\Controllers\Controller;
use App\Models\LiquidacionDocenteMensual;
use App\Models\Profesor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Liquidaciones del docente en la app (aprobadas y pagadas) y su recibo para imprimir. */
class LiquidacionController extends Controller
{
    public function __construct(private LiquidacionService $liquidaciones) {}

    public function mias(Request $request): JsonResponse
    {
        $profesor = $this->liquidaciones->profesorDe($request->user());
        abort_unless($profesor !== null, 403, 'Solo para docentes.');

        return response()->json(['data' => $this->liquidaciones->delDocente($profesor)->map(fn ($l) => $this->liquidaciones->aJson($l))->values()]);
    }

    public function show(Request $request, LiquidacionDocenteMensual $liquidacion): JsonResponse
    {
        abort_unless($this->liquidaciones->puedeVer($request->user(), $liquidacion), 403);

        return response()->json(['data' => $this->liquidaciones->aJson($liquidacion, true)]);
    }

    /** Recibo en PDF generado en el servidor (mismos datos que el HTML). */
    public function reciboPdf(Request $request, LiquidacionDocenteMensual $liquidacion): \Illuminate\Http\Response
    {
        abort_unless($this->liquidaciones->puedeVer($request->user(), $liquidacion), 403);

        return response(\App\Domain\Docentes\ReciboLiquidacionPdf::pdf($liquidacion), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.\App\Domain\Docentes\ReciboLiquidacionPdf::nombreArchivo($liquidacion).'"',
        ]);
    }

    /** HTML imprimible del recibo (la app lo convierte en PDF en el teléfono). */
    public function recibo(Request $request, LiquidacionDocenteMensual $liquidacion): JsonResponse
    {
        abort_unless($this->liquidaciones->puedeVer($request->user(), $liquidacion), 403);

        return response()->json([
            'nombre' => 'recibo-'.$liquidacion->numeroRecibo().'.pdf',
            'html' => view('liquidaciones.recibo', ['l' => $liquidacion->load('profesor', 'aprobadaPor', 'pagadaPor'), 'imprimir' => false])->render(),
        ]);
    }

    /** Gestión (administración): liquidaciones del mes de los docentes visibles para la persona. */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['anio' => 'required|integer|min:2020|max:2100', 'mes' => 'required|integer|min:1|max:12']);
        $profesores = $this->liquidaciones->profesoresVisibles($request->user());
        $liqs = LiquidacionDocenteMensual::query()->where('anio', $data['anio'])->where('mes', $data['mes'])
            ->whereIn('profesor_id', $profesores->pluck('id')->all() ?: [0])->get()->keyBy('profesor_id');

        return response()->json(['data' => $profesores->map(fn (Profesor $p) => [
            'profesor' => ['id' => $p->id, 'nombre' => $p->nombre],
            'modalidad' => $this->liquidaciones->modalidad($p),
            'liquidacion' => ($l = $liqs->get($p->id)) ? $this->liquidaciones->aJson($l) : null,
        ])->values(), 'anio' => $data['anio'], 'mes' => $data['mes'], 'puede_gestionar' => $request->user()->acceso()->puede('liquidaciones.manage')]);
    }

    /** Calcula el borrador de un docente (o de todos los de la persona) para el mes. */
    public function generar(Request $request): JsonResponse
    {
        $data = $request->validate(['anio' => 'required|integer|min:2020|max:2100', 'mes' => 'required|integer|min:1|max:12', 'profesor_id' => 'nullable|integer|exists:profesores,id']);
        if (! empty($data['profesor_id'])) {
            $l = $this->liquidaciones->generar($request->user(), Profesor::query()->findOrFail($data['profesor_id']), $data['anio'], $data['mes']);

            return response()->json(['data' => $this->liquidaciones->aJson($l)]);
        }
        $n = $this->liquidaciones->generarTodas($request->user(), $data['anio'], $data['mes']);

        return response()->json(['generadas' => $n]);
    }

    public function aprobar(Request $request, LiquidacionDocenteMensual $liquidacion): JsonResponse
    {
        return response()->json(['data' => $this->liquidaciones->aJson($this->liquidaciones->aprobar($request->user(), $liquidacion))]);
    }

    /** Marca pagada. Con registrar_gasto (por defecto sí) deja el gasto de sueldo en las sedes donde puede registrarlo. */
    public function pagar(Request $request, LiquidacionDocenteMensual $liquidacion): JsonResponse
    {
        $data = $request->validate(['registrar_gasto' => 'nullable|boolean']);
        $r = $this->liquidaciones->pagar($request->user(), $liquidacion, (bool) ($data['registrar_gasto'] ?? true));

        return response()->json(['data' => $this->liquidaciones->aJson($r['liquidacion']), 'gastos' => $r['gastos'], 'sin_gasto' => $r['sin_gasto']]);
    }
}
