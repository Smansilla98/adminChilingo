<?php

namespace App\Http\Controllers;

use App\Domain\Agenda\ConvocatoriaShow;
use App\Domain\Agenda\ShowPlanService;
use App\Models\Alumno;
use App\Models\Show;
use App\Models\ShowConfirmacion;
use Illuminate\Http\Request;

/**
 * Alumno (web): los shows a los que está convocado, con su confirmación, temas, ensayos y logística.
 */
class MiShowController extends Controller
{
    public function index(Request $request, ConvocatoriaShow $convocatoria, ShowPlanService $plan)
    {
        $items = $convocatoria->showsDe($request->user())->map(function ($i) use ($plan) {
            $conf = ShowConfirmacion::query()->where('show_id', $i['show']->id)->where('alumno_id', $i['alumno']->id)->first();

            return ['show' => $i['show'], 'confirmacion' => $conf, 'plan' => $plan->plan($i['show'])];
        });

        return view('shows.mios', compact('items'));
    }

    public function confirmar(Request $request, Show $show, ConvocatoriaShow $convocatoria)
    {
        $alumno = $convocatoria->alumnosDe($request->user())->first(fn (Alumno $a) => $convocatoria->estaConvocado($show, $a));
        abort_unless($alumno instanceof Alumno, 403, 'No estás convocado a este show.');
        abort_if($show->fecha->lt(today()), 422, 'El show ya pasó.');
        $datos = $request->validate([
            'respuesta' => 'required|in:'.implode(',', array_keys(ShowConfirmacion::RESPUESTAS)),
            'comentario' => 'nullable|string|max:255',
        ]);
        $convocatoria->responder($show, $alumno, $datos['respuesta'], $datos['comentario'] ?? null);

        return redirect()->route('alumno.shows')->with('success', '¡Gracias! Guardamos tu respuesta para «'.$show->titulo.'».');
    }
}
