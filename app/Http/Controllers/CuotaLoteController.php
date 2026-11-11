<?php

namespace App\Http\Controllers;

use App\Domain\Finanzas\CuotaLoteService;
use App\Domain\Finanzas\CuotaService;
use App\Models\Bloque;
use App\Models\Sede;
use App\Services\AmbitoSedeService;
use Illuminate\Http\Request;

/** Cuotas por lote desde la web: vista previa y generación. */
class CuotaLoteController extends Controller
{
    public function create(AmbitoSedeService $ambito)
    {
        return $this->vista($ambito, null);
    }

    public function store(Request $request, CuotaLoteService $lote, CuotaService $cuotas, AmbitoSedeService $ambito)
    {
        $datos = $request->validate($lote->reglas());

        if ($request->input('accion') !== 'generar') {
            return $this->vista($ambito, $lote->vistaPrevia($datos, $request->user()), $request);
        }

        $resultado = $lote->generar($datos, $request->user(), $cuotas);

        return redirect()->route('cuotas.index')->with('success', "Se generaron {$resultado['creadas']} cuotas. Omitidas (ya existían o sin permiso): {$resultado['omitidas']}.");
    }

    private function vista(AmbitoSedeService $ambito, ?array $previa, ?Request $request = null)
    {
        $filtro = $ambito->idsPara(auth()->user(), 'cuotas.create');
        $bloques = Bloque::query()->where('activo', true)->with('sede')->orderBy('nombre');
        $sedes = Sede::query()->orderBy('nombre');
        if ($filtro !== null) {
            $ambito->aplicarBloques($bloques, $filtro);
            $ambito->aplicarSedesCatalogo($sedes, $filtro);
        }

        return view('cuotas.lote', [
            'bloques' => $bloques->get(),
            'sedes' => $sedes->get(),
            'previa' => $previa,
            'valores' => $request?->all() ?? ['año' => (int) now()->year, 'mes_desde' => 1, 'mes_hasta' => 12, 'alcance' => 'bloque'],
        ]);
    }
}
