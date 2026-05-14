<?php

namespace App\Http\Controllers;

use App\Models\Alumno;
use App\Models\Bloque;
use App\Models\Cuota;
use App\Models\PagoDetalle;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ProfesorPagoCuotaController extends Controller
{
    /**
     * Líneas de pago (alumno + cuota) visibles para el profesor: alumnos de sus bloques y cuotas de esos bloques,
     * cuotas generales o por sede que apliquen al contexto de sus bloques.
     */
    public function index(Request $request)
    {
        $detalles = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 30);
        $alumnosFiltro = collect();

        $user = auth()->user();
        $profesor = $user?->profesor;
        if (! $profesor || ! Schema::hasTable('pago_detalles') || ! Schema::hasTable('pagos')) {
            return view('profesor.pagos-cuotas', compact('detalles', 'alumnosFiltro'));
        }

        $bIds = $profesor->bloqueIdsDondeParticipa()->map(fn ($id) => (int) $id)->unique()->values()->all();
        if ($bIds === []) {
            return view('profesor.pagos-cuotas', compact('detalles', 'alumnosFiltro'));
        }

        try {
            $sedeIds = Bloque::query()->whereIn('id', $bIds)->pluck('sede_id')->unique()->filter()->values();

            $q = PagoDetalle::query()
                ->select('pago_detalles.*')
                ->join('pagos', 'pagos.id', '=', 'pago_detalles.pago_id')
                ->with(['alumno', 'cuota.bloque.sede', 'cuota.sede', 'pago.registradoPor'])
                ->whereHas('alumno', function ($a) use ($bIds) {
                    $a->where(function ($inner) use ($bIds) {
                        if (Schema::hasTable('alumno_bloque')) {
                            $inner->whereHas('bloques', fn ($b) => $b->whereIn('bloques.id', $bIds))
                                ->orWhereIn('bloque_id', $bIds);
                        } else {
                            $inner->whereIn('bloque_id', $bIds);
                        }
                    });
                })
                ->where(function ($outer) use ($bIds, $sedeIds) {
                    $outer->whereHas('cuota', fn ($c) => $c->whereIn('bloque_id', $bIds));
                    if (Schema::hasColumn('cuotas', 'alcance')) {
                        $outer->orWhereHas('cuota', fn ($c) => $c->where('alcance', Cuota::ALCANCE_GENERAL)->whereNull('bloque_id'));
                        if ($sedeIds->isNotEmpty()) {
                            $outer->orWhereHas('cuota', fn ($c) => $c->where('alcance', Cuota::ALCANCE_SEDE)->whereIn('sede_id', $sedeIds));
                        }
                    }
                });

            if ($request->filled('alumno_id')) {
                $q->where('pago_detalles.alumno_id', $request->integer('alumno_id'));
            }
            if ($request->filled('desde')) {
                $q->whereDate('pagos.fecha_pago', '>=', $request->input('desde'));
            }
            if ($request->filled('hasta')) {
                $q->whereDate('pagos.fecha_pago', '<=', $request->input('hasta'));
            }

            $detalles = $q->orderByDesc('pagos.fecha_pago')
                ->orderByDesc('pago_detalles.id')
                ->paginate(30)
                ->withQueryString();

            $alumnosFiltro = Alumno::query()
                ->where('activo', true)
                ->where(function ($inner) use ($bIds) {
                    if (Schema::hasTable('alumno_bloque')) {
                        $inner->whereHas('bloques', fn ($b) => $b->whereIn('bloques.id', $bIds))
                            ->orWhereIn('bloque_id', $bIds);
                    } else {
                        $inner->whereIn('bloque_id', $bIds);
                    }
                })
                ->orderBy('nombre_apellido')
                ->get(['id', 'nombre_apellido']);
        } catch (QueryException $e) {
            report($e);
        }

        return view('profesor.pagos-cuotas', compact('detalles', 'alumnosFiltro'));
    }
}
