<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Agenda\ConvocatoriaShow;
use App\Domain\Agenda\ShowPlanService;
use App\Http\Controllers\Controller;
use App\Models\Show;
use App\Models\ShowEnsayo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Preparación de un show para quien lo organiza y los docentes: convocados y sus respuestas,
 * asistencia, temas, ensayos (con asistencia), logística e instrumentos.
 */
class ShowPlanController extends Controller
{
    public function catalogo(ShowPlanService $plan): JsonResponse
    {
        return response()->json($plan->catalogo());
    }

    public function convocados(Show $show, ConvocatoriaShow $convocatoria): JsonResponse
    {
        $this->authorize('view', $show);

        return response()->json(['data' => $convocatoria->planilla($show), 'resumen' => $convocatoria->resumen($show)]);
    }

    public function asistencia(Request $request, Show $show, ConvocatoriaShow $convocatoria): JsonResponse
    {
        $this->authorize('asistencia', $show);
        $datos = $request->validate(['marcas' => 'required|array', 'marcas.*' => 'nullable|boolean']);
        $convocatoria->marcarAsistencia($show, $datos['marcas']);

        return response()->json(['data' => $convocatoria->planilla($show), 'resumen' => $convocatoria->resumen($show)]);
    }

    public function logistica(Request $request, Show $show, ShowPlanService $plan): JsonResponse
    {
        $this->authorize('update', $show);
        $plan->guardarLogistica($show, $request->validate(ShowPlanService::reglasLogistica()));

        return response()->json(['data' => $plan->plan($show->fresh() ?? $show)]);
    }

    public function temas(Request $request, Show $show, ShowPlanService $plan): JsonResponse
    {
        $this->authorize('update', $show);
        $plan->guardarTemas($show, $request->validate(ShowPlanService::reglasTemas())['temas']);

        return response()->json(['data' => $plan->plan($show->fresh() ?? $show)]);
    }

    public function instrumentos(Request $request, Show $show, ShowPlanService $plan): JsonResponse
    {
        $this->authorize('update', $show);
        $plan->guardarInstrumentos($show, $request->validate(ShowPlanService::reglasInstrumentos())['instrumentos']);

        return response()->json(['data' => $plan->plan($show->fresh() ?? $show)]);
    }

    public function crearEnsayo(Request $request, Show $show, ShowPlanService $plan): JsonResponse
    {
        $this->authorize('update', $show);
        $ensayo = $plan->guardarEnsayo($show, null, $request->validate(ShowPlanService::reglasEnsayo()));

        return response()->json(['data' => $this->ensayo($ensayo, $plan)], 201);
    }

    public function verEnsayo(ShowEnsayo $ensayo, ShowPlanService $plan): JsonResponse
    {
        $this->authorize('view', $ensayo->show);

        return response()->json(['data' => $this->ensayo($ensayo, $plan)]);
    }

    public function actualizarEnsayo(Request $request, ShowEnsayo $ensayo, ShowPlanService $plan): JsonResponse
    {
        $this->authorize('update', $ensayo->show);
        $plan->guardarEnsayo($ensayo->show, $ensayo, $request->validate(ShowPlanService::reglasEnsayo()));

        return response()->json(['data' => $this->ensayo($ensayo->fresh() ?? $ensayo, $plan)]);
    }

    public function eliminarEnsayo(ShowEnsayo $ensayo, ShowPlanService $plan): JsonResponse
    {
        $this->authorize('update', $ensayo->show);
        $plan->eliminarEnsayo($ensayo);

        return response()->json(['ok' => true]);
    }

    public function asistenciaEnsayo(Request $request, ShowEnsayo $ensayo, ShowPlanService $plan): JsonResponse
    {
        $this->authorize('asistencia', $ensayo->show);
        $datos = $request->validate(['marcas' => 'required|array', 'marcas.*' => 'boolean']);
        $plan->convocatoria()->marcarAsistenciaEnsayo($ensayo, $datos['marcas']);

        return response()->json(['data' => $this->ensayo($ensayo, $plan)]);
    }

    /** @return array<string, mixed> */
    private function ensayo(ShowEnsayo $e, ShowPlanService $plan): array
    {
        return [
            'id' => $e->id,
            'show_id' => $e->show_id,
            'show' => $e->show->titulo,
            'fecha' => $e->fecha->toDateString(),
            'hora_inicio' => $e->hora_inicio?->format('H:i'),
            'hora_fin' => $e->hora_fin?->format('H:i'),
            'lugar' => $e->lugar,
            'notas' => $e->notas,
            'asistencia' => $plan->convocatoria()->planillaEnsayo($e),
            'acciones' => ['editar' => request()->user()->can('update', $e->show), 'asistencia' => request()->user()->can('asistencia', $e->show)],
        ];
    }
}
