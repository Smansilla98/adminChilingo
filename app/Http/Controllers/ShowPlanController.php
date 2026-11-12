<?php

namespace App\Http\Controllers;

use App\Domain\Agenda\ConvocatoriaShow;
use App\Domain\Agenda\ShowPlanService;
use App\Models\Auditoria;
use App\Models\Show;
use App\Models\ShowEnsayo;
use App\Models\ShowInstrumento;
use App\Models\ShowTema;
use Illuminate\Http\Request;

/**
 * Preparación de un show desde el panel web: asistencia, temas, ensayos, logística e instrumentos.
 */
class ShowPlanController extends Controller
{
    public function asistencia(Request $request, Show $show, ConvocatoriaShow $convocatoria)
    {
        $this->authorize('asistencia', $show);
        $datos = $request->validate(['marcas' => 'required|array', 'marcas.*' => 'nullable|in:0,1']);
        $convocatoria->marcarAsistencia($show, $datos['marcas']);

        return back()->with('success', 'Asistencia al show guardada.');
    }

    public function logistica(Request $request, Show $show, ShowPlanService $plan)
    {
        $this->authorize('update', $show);
        $plan->guardarLogistica($show, $request->validate(ShowPlanService::reglasLogistica()));

        return back()->with('success', 'Logística guardada.');
    }

    public function agregarTema(Request $request, Show $show)
    {
        $this->authorize('update', $show);
        $datos = $request->validate([
            'programa_ritmo_id' => 'nullable|integer|exists:programa_ritmos,id',
            'titulo' => 'nullable|string|max:255|required_without:programa_ritmo_id',
            'notas' => 'nullable|string|max:255',
        ]);
        ShowTema::query()->create($datos + ['show_id' => $show->id, 'orden' => (int) $show->temas()->max('orden') + 1]);
        Auditoria::registrar('show.temas', $show);

        return back()->with('success', 'Tema agregado.');
    }

    public function quitarTema(Show $show, ShowTema $tema)
    {
        $this->authorize('update', $show);
        abort_unless((int) $tema->show_id === (int) $show->id, 404);
        $tema->delete();

        return back()->with('success', 'Tema quitado.');
    }

    public function agregarInstrumento(Request $request, Show $show)
    {
        $this->authorize('update', $show);
        $datos = $request->validate([
            'inventario_item_id' => 'nullable|integer|exists:inventario_items,id',
            'descripcion' => 'nullable|string|max:255|required_without:inventario_item_id',
            'cantidad' => 'nullable|integer|min:1|max:999',
        ]);
        ShowInstrumento::query()->create(['show_id' => $show->id, 'cantidad' => (int) ($datos['cantidad'] ?? 1)] + $datos);
        Auditoria::registrar('show.instrumentos', $show);

        return back()->with('success', 'Instrumento agregado.');
    }

    public function quitarInstrumento(Show $show, ShowInstrumento $instrumento)
    {
        $this->authorize('update', $show);
        abort_unless((int) $instrumento->show_id === (int) $show->id, 404);
        $instrumento->delete();

        return back()->with('success', 'Instrumento quitado.');
    }

    public function crearEnsayo(Request $request, Show $show, ShowPlanService $plan)
    {
        $this->authorize('update', $show);
        $plan->guardarEnsayo($show, null, $request->validate(ShowPlanService::reglasEnsayo()));

        return back()->with('success', 'Ensayo agregado.');
    }

    public function verEnsayo(ShowEnsayo $ensayo, ConvocatoriaShow $convocatoria)
    {
        $this->authorize('view', $ensayo->show);

        return view('shows.ensayo', ['ensayo' => $ensayo, 'show' => $ensayo->show, 'planilla' => $convocatoria->planillaEnsayo($ensayo)]);
    }

    public function asistenciaEnsayo(Request $request, ShowEnsayo $ensayo, ConvocatoriaShow $convocatoria)
    {
        $this->authorize('asistencia', $ensayo->show);
        $validado = $request->validate(['marcas' => 'nullable|array', 'marcas.*' => 'in:0,1']);
        $marcas = (array) ($validado['marcas'] ?? []);
        // Los que no se marcaron como presentes quedan ausentes.
        $todos = collect($convocatoria->planillaEnsayo($ensayo))->mapWithKeys(fn ($f) => [$f['alumno_id'] => (bool) ($marcas[$f['alumno_id']] ?? false)])->all();
        $convocatoria->marcarAsistenciaEnsayo($ensayo, $todos);

        return back()->with('success', 'Asistencia del ensayo guardada.');
    }

    public function eliminarEnsayo(ShowEnsayo $ensayo, ShowPlanService $plan)
    {
        $show = $ensayo->show;
        $this->authorize('update', $show);
        $plan->eliminarEnsayo($ensayo);

        return redirect()->route('shows.show', $show)->with('success', 'Ensayo eliminado.');
    }
}
