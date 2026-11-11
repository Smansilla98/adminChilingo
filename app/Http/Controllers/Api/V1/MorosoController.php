<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Finanzas\MorosidadService;
use App\Domain\Notificaciones\NotificacionService;
use App\Http\Controllers\Controller;
use App\Models\Alumno;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Morosidad (API): lista con antigüedad y recordatorios. Mismas reglas que la web. */
class MorosoController extends Controller
{
    public function index(Request $request, MorosidadService $morosidad): JsonResponse
    {
        abort_unless($request->user()->acceso()->puede('pagos.view'), 403);
        $filtros = $request->validate([
            'sede_id' => 'nullable|integer|exists:sedes,id',
            'bloque_id' => 'nullable|integer|exists:bloques,id',
            'tramo' => 'nullable|in:'.implode(',', array_keys(MorosidadService::TRAMOS)),
        ]);
        $deudores = $morosidad->deudores($filtros, $request->user());

        return response()->json([
            'data' => $deudores->values(),
            'total_saldo' => round((float) $deudores->sum('saldo_vencido'), 2),
            'tramos' => collect(MorosidadService::TRAMOS)->map(fn ($v, $k) => ['id' => $k, 'nombre' => $v])->values(),
        ]);
    }

    public function recordar(Request $request, Alumno $alumno, MorosidadService $morosidad): JsonResponse
    {
        abort_unless($request->user()->acceso()->puedeAlguno(['notificaciones.send', 'pagos.view']), 403);

        return response()->json(['data' => $morosidad->recordar($alumno, $request->user(), $this->canales($request))]);
    }

    public function recordarMasivo(Request $request, MorosidadService $morosidad): JsonResponse
    {
        abort_unless($request->user()->acceso()->puedeAlguno(['notificaciones.send', 'pagos.view']), 403);
        $datos = $request->validate(['alumno_ids' => 'required|array|min:1|max:500', 'alumno_ids.*' => 'integer|exists:alumnos,id']);

        return response()->json(['data' => $morosidad->recordarMasivo($datos['alumno_ids'], $request->user(), $this->canales($request))]);
    }

    /** @return list<string> */
    private function canales(Request $request): array
    {
        $canales = $request->validate(['canales' => 'nullable|array', 'canales.*' => 'in:'.implode(',', NotificacionService::CANALES)])['canales'] ?? [];

        return $canales !== [] ? array_values(array_unique($canales)) : ['interna', 'push'];
    }
}
