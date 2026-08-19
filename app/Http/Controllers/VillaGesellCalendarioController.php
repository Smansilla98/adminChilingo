<?php

namespace App\Http\Controllers;

use App\Models\VillaGesellDia;
use App\Models\VillaGesellTocada;
use App\Services\VillaGesellGiraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VillaGesellCalendarioController extends Controller
{
    public function __construct(private VillaGesellGiraService $gira) {}

    public function index(): View
    {
        $this->gira->asegurarDias();
        $config = $this->gira->config();
        $dias = VillaGesellDia::query()
            ->with('tocadas')
            ->whereBetween('fecha', [$config->fecha_inicio, $config->fecha_fin])
            ->orderBy('fecha')
            ->get();

        return view('villa-gesell.calendario.index', compact('config', 'dias'));
    }

    public function updateDia(Request $request, VillaGesellDia $dia): RedirectResponse
    {
        $data = $request->validate([
            'notas' => ['nullable', 'string', 'max:400'],
        ]);
        $dia->update($data);

        return back()->with('success', 'Notas del día guardadas.');
    }

    public function generarSlots(Request $request, VillaGesellDia $dia): RedirectResponse
    {
        $data = $request->validate([
            'cantidad' => ['required', 'integer', 'min:1', 'max:12'],
        ]);
        $orden = (int) $dia->tocadas()->max('orden') + 1;
        for ($i = 0; $i < $data['cantidad']; $i++) {
            VillaGesellTocada::query()->create([
                'dia_id' => $dia->id,
                'orden' => $orden + $i,
                'hora' => null,
                'que' => 'Por definir',
                'donde' => null,
            ]);
        }

        return back()->with('success', 'Se generaron '.$data['cantidad'].' fechas en el día.');
    }

    public function storeTocada(Request $request, VillaGesellDia $dia): RedirectResponse
    {
        $data = $this->validatedTocada($request);
        $data['dia_id'] = $dia->id;
        $data['orden'] = $data['orden'] ?: ((int) $dia->tocadas()->max('orden') + 1);
        VillaGesellTocada::query()->create($data);

        return back()->with('success', 'Fecha agregada.');
    }

    public function updateTocada(Request $request, VillaGesellTocada $tocada): RedirectResponse
    {
        $tocada->update($this->validatedTocada($request));

        return back()->with('success', 'Fecha actualizada.');
    }

    public function destroyTocada(VillaGesellTocada $tocada): RedirectResponse
    {
        $tocada->delete();

        return back()->with('success', 'Fecha eliminada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedTocada(Request $request): array
    {
        $data = $request->validate([
            'orden' => ['nullable', 'integer', 'min:1', 'max:99'],
            'hora' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'que' => ['required', 'string', 'max:160'],
            'donde' => ['nullable', 'string', 'max:160'],
            'notas' => ['nullable', 'string', 'max:400'],
        ]);
        $data['orden'] = isset($data['orden']) ? (int) $data['orden'] : null;

        return $data;
    }
}
