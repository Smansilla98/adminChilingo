<?php

namespace App\Domain\Finanzas;

use App\Models\Alumno;
use App\Models\Bloque;
use App\Models\Cuota;
use App\Models\PagoDetalle;
use App\Models\Profesor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que ve un docente de los pagos: líneas de pago de alumnos de sus bloques por
 * cuotas de esos bloques, generales o de sus sedes (con el abono que le corresponde).
 */
class PagosDocenteService
{
    /** @return list<int> */
    public function bloques(Profesor $profesor): array
    {
        return $profesor->bloqueIdsDondeParticipa()->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * @param  array{alumno_id?: int|null, desde?: string|null, hasta?: string|null}  $filtros
     */
    public function lineas(Profesor $profesor, array $filtros = []): ?Builder
    {
        $bIds = $this->bloques($profesor);
        if ($bIds === [] || ! Schema::hasTable('pago_detalles') || ! Schema::hasTable('pagos')) {
            return null;
        }
        $sedeIds = Bloque::query()->whereIn('id', $bIds)->pluck('sede_id')->unique()->filter()->values();

        $q = PagoDetalle::query()
            ->select('pago_detalles.*')
            ->join('pagos', 'pagos.id', '=', 'pago_detalles.pago_id')
            ->with(['alumno', 'cuota.bloque.sede', 'cuota.sede', 'pago.registradoPor'])
            ->whereHas('alumno', fn ($a) => $this->deSusBloques($a, $bIds))
            ->where(function ($outer) use ($bIds, $sedeIds) {
                $outer->whereHas('cuota', fn ($c) => $c->whereIn('bloque_id', $bIds));
                if (Schema::hasColumn('cuotas', 'alcance')) {
                    $outer->orWhereHas('cuota', fn ($c) => $c->where('alcance', Cuota::ALCANCE_GENERAL)->whereNull('bloque_id'));
                    if ($sedeIds->isNotEmpty()) {
                        $outer->orWhereHas('cuota', fn ($c) => $c->where('alcance', Cuota::ALCANCE_SEDE)->whereIn('sede_id', $sedeIds));
                    }
                }
            });

        if (! empty($filtros['alumno_id'])) {
            $q->where('pago_detalles.alumno_id', (int) $filtros['alumno_id']);
        }
        if (! empty($filtros['desde'])) {
            $q->whereDate('pagos.fecha_pago', '>=', $filtros['desde']);
        }
        if (! empty($filtros['hasta'])) {
            $q->whereDate('pagos.fecha_pago', '<=', $filtros['hasta']);
        }

        return $q->orderByDesc('pagos.fecha_pago')->orderByDesc('pago_detalles.id');
    }

    /** @return Collection<int, Alumno> */
    public function alumnos(Profesor $profesor): Collection
    {
        $bIds = $this->bloques($profesor);
        if ($bIds === []) {
            return collect();
        }

        return Alumno::query()->where('activo', true)->where(fn ($inner) => $this->deSusBloques($inner, $bIds))
            ->orderBy('nombre_apellido')->get(['id', 'nombre_apellido']);
    }

    /**
     * @param  list<int>  $bIds
     */
    private function deSusBloques(Builder $query, array $bIds): Builder
    {
        return $query->where(function ($inner) use ($bIds) {
            if (Schema::hasTable('alumno_bloque')) {
                $inner->whereHas('bloques', fn ($b) => $b->whereIn('bloques.id', $bIds))->orWhereIn('bloque_id', $bIds);
            } else {
                $inner->whereIn('bloque_id', $bIds);
            }
        });
    }
}
