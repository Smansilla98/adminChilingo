<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Finanzas\CuotaLoteService;
use App\Domain\Finanzas\CuotaService;
use App\Http\Controllers\Controller;
use App\Models\Cuota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Cuotas por lote (API): vista previa sin escribir y generación. Requiere cuotas.create. */
class CuotaLoteController extends Controller
{
    public function vistaPrevia(Request $request, CuotaLoteService $lote): JsonResponse
    {
        $this->authorize('create', Cuota::class);
        $datos = $request->validate($lote->reglas());

        return response()->json(['data' => $lote->vistaPrevia($datos, $request->user())]);
    }

    public function generar(Request $request, CuotaLoteService $lote, CuotaService $cuotas): JsonResponse
    {
        $this->authorize('create', Cuota::class);
        $datos = $request->validate($lote->reglas());

        return response()->json(['data' => $lote->generar($datos, $request->user(), $cuotas)], 201);
    }
}
