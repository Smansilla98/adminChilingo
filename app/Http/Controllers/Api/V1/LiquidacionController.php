<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Docentes\LiquidacionService;
use App\Http\Controllers\Controller;
use App\Models\LiquidacionDocenteMensual;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Liquidaciones del docente en la app (aprobadas y pagadas) y su recibo para imprimir. */
class LiquidacionController extends Controller
{
    public function __construct(private LiquidacionService $liquidaciones) {}

    public function mias(Request $request): JsonResponse
    {
        $profesor = $this->liquidaciones->profesorDe($request->user());
        abort_unless($profesor !== null, 403, 'Solo para docentes.');

        return response()->json(['data' => $this->liquidaciones->delDocente($profesor)->map(fn ($l) => $this->liquidaciones->aJson($l))->values()]);
    }

    public function show(Request $request, LiquidacionDocenteMensual $liquidacion): JsonResponse
    {
        abort_unless($this->liquidaciones->puedeVer($request->user(), $liquidacion), 403);

        return response()->json(['data' => $this->liquidaciones->aJson($liquidacion, true)]);
    }

    /** HTML imprimible del recibo (la app lo convierte en PDF en el teléfono). */
    public function recibo(Request $request, LiquidacionDocenteMensual $liquidacion): JsonResponse
    {
        abort_unless($this->liquidaciones->puedeVer($request->user(), $liquidacion), 403);

        return response()->json([
            'nombre' => 'recibo-'.$liquidacion->numeroRecibo().'.pdf',
            'html' => view('liquidaciones.recibo', ['l' => $liquidacion->load('profesor', 'aprobadaPor', 'pagadaPor'), 'imprimir' => false])->render(),
        ]);
    }
}
