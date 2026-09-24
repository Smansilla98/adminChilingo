<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Finanzas\PagoService;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PagoResource;
use App\Models\Cuota;
use App\Models\Pago;
use App\Services\AmbitoSedeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Consulta financiera para la app (contador, tesorero, administración). El registro de
 * pagos con liquidación docente sigue en el panel web; desde la app se consulta y se anula.
 */
class FinanzasController extends Controller
{
    public function cuotas(Request $request, AmbitoSedeService $ambito): JsonResponse
    {
        $this->authorize('viewAny', Cuota::class);
        $query = Cuota::query()->with(['bloque:id,nombre', 'sede:id,nombre'])->withCount('pagoDetalles')
            ->where('año', $request->integer('anio') ?: (int) now()->year)
            ->orderBy('mes')->orderBy('id');
        $filtro = $ambito->idsPara($request->user(), 'cuotas.view');
        if ($filtro !== null) {
            $ambito->aplicarCuotas($query, $filtro);
        }

        return response()->json(['data' => $query->get()->map(fn (Cuota $c) => [
            'id' => $c->id,
            'nombre' => $c->nombre,
            'anio' => (int) $c->año,
            'mes' => $c->mes,
            'monto' => (float) $c->monto,
            'vencimiento' => $c->fecha_vencimiento?->toDateString(),
            'alcance' => $c->alcanceNormalizado(),
            'bloque' => $c->bloque?->nombre,
            'sede' => $c->sede?->nombre,
            'pagos' => $c->pago_detalles_count,
            'activo' => (bool) $c->activo,
        ])->values()]);
    }

    public function pagos(Request $request, AmbitoSedeService $ambito): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Pago::class);
        $query = Pago::query()->with(['detalles.alumno:id,nombre_apellido', 'detalles.cuota:id,nombre', 'registradoPor:id,name'])
            ->orderByDesc('fecha_pago')->orderByDesc('id');
        $filtro = $ambito->idsPara($request->user(), 'pagos.view');
        if ($filtro !== null) {
            $ambito->aplicarPagos($query, $filtro);
        }
        if ($request->filled('desde')) {
            $query->where('fecha_pago', '>=', $request->date('desde'));
        }
        if ($request->filled('hasta')) {
            $query->where('fecha_pago', '<=', $request->date('hasta'));
        }

        return PagoResource::collection($query->paginate(30));
    }

    public function pago(Request $request, Pago $pago): PagoResource
    {
        $this->authorize('view', $pago);

        return new PagoResource($pago->load(['detalles.alumno:id,nombre_apellido', 'detalles.cuota:id,nombre', 'registradoPor:id,name']));
    }

    public function anular(Request $request, Pago $pago, PagoService $pagos): PagoResource
    {
        $this->authorize('reverse', $pago);
        $data = $request->validate(['motivo' => 'required|string|min:5|max:500']);

        return new PagoResource($pagos->anular($pago, $request->user(), $data['motivo'])->load('detalles'));
    }
}
