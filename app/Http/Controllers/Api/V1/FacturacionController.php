<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Finanzas\FacturacionService;
use App\Domain\Operativo\CierreMesService;
use App\Http\Controllers\Controller;
use App\Models\FacturacionMensual;
use App\Models\Sede;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Facturación mensual por sede y cierre de mes. Mismas reglas que el panel web
 * (FacturacionService, CierreMesService). La API usa `anio` en lugar de `año`.
 */
class FacturacionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $acceso = $request->user()->acceso();
        $query = FacturacionMensual::query()->with('sede:id,nombre');
        $acceso->alcance('facturacion.view')->aplicarPorSede($query, 'sede_id', false, $acceso->puedeGlobal('facturacion.view'));
        if ($request->filled('sede_id')) {
            $query->where('sede_id', $request->integer('sede_id'));
        }
        if ($request->filled('anio')) {
            $query->where('año', $request->integer('anio'));
        }
        $totales = (clone $query)->selectRaw('SUM(monto_facturado) as facturado, SUM(monto_previsto) as previsto, SUM(cantidad_alumnos) as alumnos')->first();
        $pagina = $query->orderByDesc('año')->orderByDesc('mes')->paginate(24);

        return response()->json([
            'data' => collect($pagina->items())->map(fn (FacturacionMensual $f) => $this->fila($request, $f))->values(),
            'meta' => ['current_page' => $pagina->currentPage(), 'last_page' => $pagina->lastPage(), 'total' => $pagina->total()],
            'totales' => [
                'facturado' => round((float) ($totales->facturado ?? 0), 2),
                'previsto' => round((float) ($totales->previsto ?? 0), 2),
            ],
        ]);
    }

    public function show(Request $request, FacturacionMensual $facturacion): JsonResponse
    {
        $this->asegurarVista($request, $facturacion);

        return response()->json(['data' => $this->fila($request, $facturacion->load('sede:id,nombre'))]);
    }

    public function store(Request $request, FacturacionService $servicio): JsonResponse
    {
        $reglas = $servicio->reglas();
        $reglas['anio'] = $reglas['año'];
        unset($reglas['año']);
        $datos = $request->validate($reglas);
        $datos['año'] = $datos['anio'];
        unset($datos['anio']);
        $f = $servicio->registrar($datos, $request->user());

        return response()->json(['data' => $this->fila($request, $f->load('sede:id,nombre'))], 201);
    }

    public function update(Request $request, FacturacionMensual $facturacion, FacturacionService $servicio): JsonResponse
    {
        $servicio->actualizar($facturacion, $request->validate($servicio->reglas(true)), $request->user());

        return response()->json(['data' => $this->fila($request, $facturacion->fresh('sede'))]);
    }

    public function catalogo(Request $request): JsonResponse
    {
        $acceso = $request->user()->acceso();
        $sedes = Sede::query()->where('activo', true)->orderBy('nombre');
        $acceso->alcance('facturacion.manage')->aplicarPorSede($sedes, 'id');

        return response()->json([
            'meses' => FacturacionMensual::nombresMeses(),
            'sedes' => $acceso->puede('facturacion.manage') ? $sedes->get(['id', 'nombre']) : [],
            'puede_general' => $acceso->puedeGlobal('facturacion.manage'),
            'puede_cierre' => $acceso->puedeGlobal('facturacion.view'),
        ]);
    }

    /** Checklist del cierre de mes (toda la escuela). */
    public function cierreMes(Request $request, CierreMesService $cierre): JsonResponse
    {
        abort_unless($request->user()->acceso()->puedeGlobal('facturacion.view'), 403, 'El cierre de mes es de toda la escuela.');
        $mes = max(1, min(12, $request->integer('mes', (int) now()->month)));
        $anio = $request->integer('anio', (int) now()->year);
        $items = $cierre->checklist($mes, $anio);

        return response()->json([
            'mes' => $mes,
            'anio' => $anio,
            'items' => $items,
            'ok' => collect($items)->where('ok', true)->count(),
            'total' => collect($items)->whereNotNull('ok')->count(),
        ]);
    }

    private function asegurarVista(Request $request, FacturacionMensual $f): void
    {
        $acceso = $request->user()->acceso();
        $ok = $f->sede_id ? $acceso->puedeEnSede('facturacion.view', (int) $f->sede_id) : $acceso->puedeGlobal('facturacion.view');
        abort_unless($ok, 403, 'No tenés acceso a esta facturación.');
    }

    /** @return array<string, mixed> */
    private function fila(Request $request, FacturacionMensual $f): array
    {
        $previsto = $f->monto_previsto !== null ? (float) $f->monto_previsto : null;

        return [
            'id' => $f->id,
            'anio' => (int) $f->año,
            'mes' => (int) $f->mes,
            'mes_nombre' => $f->nombre_mes,
            'sede' => $f->sede ? ['id' => $f->sede->id, 'nombre' => $f->sede->nombre] : null,
            'cantidad_alumnos' => (int) $f->cantidad_alumnos,
            'monto_facturado' => (float) $f->monto_facturado,
            'monto_previsto' => $previsto,
            'diferencia' => $previsto !== null ? round((float) $f->monto_facturado - $previsto, 2) : null,
            'notas' => $f->notas,
            'puede_editar' => app(FacturacionService::class)->puedeGestionar($request->user(), $f->sede_id),
        ];
    }
}
