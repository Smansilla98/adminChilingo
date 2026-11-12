<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Reportes\TableroDireccionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Tablero de dirección para la app: el mismo cálculo que la web (TableroDireccionService). */
class DireccionController extends Controller
{
    public function __construct(private TableroDireccionService $tablero) {}

    public function index(Request $request): JsonResponse
    {
        $sedeId = $request->integer('sede_id') ?: null;
        $d = $this->tablero->datos($request->user(), $sedeId);

        return response()->json([
            'alcance' => $d['alcance'],
            'sedes' => $this->tablero->sedesDisponibles($request->user())->map(fn ($s) => ['id' => $s->id, 'nombre' => $s->nombre])->values(),
            'sede_id' => $sedeId,
            'generado' => $d['generado'],
            'actual' => $d['actual'],
            'totales_12m' => $d['totales_12m'],
            'alumnos' => $d['alumnos'],
            'morosidad' => ['total' => $d['morosidad']['total'], 'alumnos' => $d['morosidad']['alumnos'], 'porcentaje_alumnos' => $d['morosidad']['porcentaje_alumnos']],
            'ocupacion' => ['inscriptos' => $d['ocupacion']['inscriptos'], 'cupo' => $d['ocupacion']['cupo'], 'porcentaje' => $d['ocupacion']['porcentaje']],
            'asistencia_promedio' => $d['asistencia_promedio'],
            'proyeccion' => $d['proyeccion'],
            'serie' => $d['serie'],
        ]);
    }
}
