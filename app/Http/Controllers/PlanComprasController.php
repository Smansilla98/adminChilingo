<?php

namespace App\Http\Controllers;

use App\Domain\Compras\PlanComprasService;

class PlanComprasController extends Controller
{
    /**
     * Muestra, por sede, la trazabilidad básica y una sugerencia de compra
     * de instrumentos y parches según alumnos, carga horaria y stock.
     */
    public function index(PlanComprasService $plan)
    {
        return view('plan-compras.index', [
            'sedesDatos' => $plan->calcular(auth()->user()),
            'ratioObjetivo' => PlanComprasService::RATIO_OBJETIVO,
            'parchesBase' => PlanComprasService::PARCHES_BASE,
        ]);
    }
}
