<?php

namespace App\Domain\Personas;

use App\Models\Alumno;
use App\Models\Cuota;
use App\Models\PagoDetalle;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Estado de cuenta del alumno: cuotas que le aplican (por bloque, sede o general),
 * su situación (pagada, vencida, pendiente) y el historial de pagos.
 */
class EstadoCuentaAlumnoService
{
    /**
     * @return array{estadoCuenta: Collection, historialPagos: Collection}
     */
    public function para(Alumno $alumno): array
    {
        $cuotasAplicables = $this->cuotasAplicables($alumno);

        try {
            $historialPagos = PagoDetalle::query()
                ->with(['pago', 'cuota'])
                ->where('alumno_id', $alumno->id)
                ->whereHas('pago')
                ->get()
                ->sortByDesc(fn ($d) => $d->pago?->fecha_pago)
                ->values();
        } catch (QueryException $e) {
            $historialPagos = collect();
        }

        $pagosPorCuota = $historialPagos->keyBy('cuota_id');

        $hoy = Carbon::today();
        $estadoCuenta = $cuotasAplicables->map(function (Cuota $cuota) use ($pagosPorCuota, $hoy) {
            $pagoDetalle = $pagosPorCuota->get($cuota->id);

            $fechaPago = $pagoDetalle?->pago?->fecha_pago;
            $fechaVencimiento = $cuota->fecha_vencimiento;

            if ($fechaPago) {
                $estado = 'Pagada';
                $estadoColor = 'success';
            } elseif ($fechaVencimiento && $fechaVencimiento->lt($hoy)) {
                $estado = 'Vencida';
                $estadoColor = 'danger';
            } else {
                $estado = 'Pendiente';
                $estadoColor = 'warning';
            }

            return [
                'cuota' => $cuota,
                'periodo' => ($cuota->mes ? str_pad((string) $cuota->mes, 2, '0', STR_PAD_LEFT) : '—').'/'.($cuota->año ?? '—'),
                'monto' => (float) $cuota->monto,
                'fecha_pago' => $fechaPago,
                'estado' => $estado,
                'estado_color' => $estadoColor,
            ];
        });

        return compact('estadoCuenta', 'historialPagos');
    }

    /** @return Collection<int, Cuota> */
    private function cuotasAplicables(Alumno $alumno): Collection
    {
        $bloquesIds = $alumno->bloques->pluck('id')->filter()->values();
        if ($bloquesIds->isEmpty() && $alumno->bloque_id) {
            $bloquesIds = collect([$alumno->bloque_id]);
        }

        $cuotas = collect();
        if ($bloquesIds->isNotEmpty()) {
            $q = Cuota::query()
                ->with(['alumnos:id'])
                ->where('activo', true);

            if (Schema::hasColumn('cuotas', 'alcance')) {
                $sidList = $alumno->bloques->pluck('sede_id')->filter()->unique()->values()->all();
                if ($alumno->sede_id) {
                    $sidList = array_values(array_unique(array_merge($sidList, [(int) $alumno->sede_id])));
                }
                $q->where(function ($outer) use ($bloquesIds, $sidList) {
                    $outer->whereIn('bloque_id', $bloquesIds->all())
                        ->orWhere(function ($g) {
                            $g->where('alcance', Cuota::ALCANCE_GENERAL)->whereNull('bloque_id');
                        });
                    if ($sidList !== []) {
                        $outer->orWhere(function ($s) use ($sidList) {
                            $s->where('alcance', Cuota::ALCANCE_SEDE)->whereIn('sede_id', $sidList);
                        });
                    }
                });
            } else {
                $q->whereIn('bloque_id', $bloquesIds->all());
            }

            $cuotas = $q->orderByDesc('año')->orderByDesc('mes')->get();
        }

        return $cuotas->filter(function (Cuota $cuota) use ($alumno) {
            if ($cuota->alumnos->isEmpty()) {
                return true;
            }

            return $cuota->alumnos->contains('id', $alumno->id);
        })->values();
    }
}
