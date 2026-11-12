<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Agenda\ConvocatoriaShow;
use App\Domain\Agenda\ShowPlanService;
use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Show;
use App\Models\ShowConfirmacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shows a los que está convocado el alumno: confirmar si va, ver temas, ensayos y logística.
 */
class MiShowController extends Controller
{
    public function index(Request $request, ConvocatoriaShow $convocatoria): JsonResponse
    {
        $items = $convocatoria->showsDe($request->user(), ! $request->boolean('todos'));

        return response()->json(['data' => $items->map(fn ($i) => $this->presentar($i['show'], $i['alumno']))->values()]);
    }

    public function show(Request $request, Show $show, ConvocatoriaShow $convocatoria, ShowPlanService $plan): JsonResponse
    {
        $alumno = $this->alumnoConvocado($request, $show, $convocatoria);

        return response()->json(['data' => $this->presentar($show, $alumno) + $plan->plan($show)]);
    }

    public function confirmar(Request $request, Show $show, ConvocatoriaShow $convocatoria, ShowPlanService $plan): JsonResponse
    {
        $alumno = $this->alumnoConvocado($request, $show, $convocatoria);
        abort_if($show->fecha->lt(today()), 422, 'El show ya pasó.');
        $datos = $request->validate([
            'respuesta' => 'required|in:'.implode(',', array_keys(ShowConfirmacion::RESPUESTAS)),
            'comentario' => 'nullable|string|max:255',
        ]);
        $convocatoria->responder($show, $alumno, $datos['respuesta'], $datos['comentario'] ?? null);

        return response()->json(['data' => $this->presentar($show, $alumno) + $plan->plan($show)]);
    }

    private function alumnoConvocado(Request $request, Show $show, ConvocatoriaShow $convocatoria): Alumno
    {
        $alumno = $convocatoria->alumnosDe($request->user())->first(fn (Alumno $a) => $convocatoria->estaConvocado($show, $a));
        abort_unless($alumno instanceof Alumno, 403, 'No estás convocado a este show.');

        return $alumno;
    }

    /** @return array<string, mixed> */
    private function presentar(Show $s, Alumno $alumno): array
    {
        $conf = ShowConfirmacion::query()->where('show_id', $s->id)->where('alumno_id', $alumno->id)->first();

        return [
            'id' => $s->id,
            'titulo' => $s->titulo,
            'fecha' => $s->fecha->toDateString(),
            'hora_inicio' => $s->hora_inicio?->format('H:i'),
            'hora_fin' => $s->hora_fin?->format('H:i'),
            'lugar' => $s->lugar,
            'descripcion' => $s->descripcion,
            'convocatoria_abierta' => (bool) $s->convocatoria_abierta,
            'alumno_id' => $alumno->id,
            'mi_respuesta' => $conf?->respuesta,
            'mi_comentario' => $conf?->comentario,
            'asisti' => $conf?->asistio,
        ];
    }
}
