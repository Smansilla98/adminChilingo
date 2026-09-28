<?php

namespace App\Http\Controllers;

use App\Domain\Asistencias\SeguimientoService;
use App\Models\ObservacionPedagogica;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SeguimientoPedagogicoController extends Controller
{
    public function store(Request $request, SeguimientoService $seguimiento)
    {
        if (! Schema::hasTable('observaciones_pedagogicas')) {
            return back()->withErrors(['cuerpo' => 'Falta migrar la bitácora pedagógica.'])->withInput();
        }
        $seguimiento->registrar($request->user(), $request->validate($seguimiento->reglas()), $request->boolean('visible_alumno'));

        return back()->with('success', 'Quedó registrada la nota pedagógica.');
    }

    public function destroy(ObservacionPedagogica $observacionPedagogica, SeguimientoService $seguimiento)
    {
        $seguimiento->eliminar(auth()->user(), $observacionPedagogica);

        return back()->with('success', 'Nota pedagógica eliminada.');
    }
}
