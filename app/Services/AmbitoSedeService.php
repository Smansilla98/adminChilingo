<?php

namespace App\Services;

use App\Models\Cuota;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Aísla datos operativos/financieros a las sedes del coordinador.
 * null = sin filtro (admin / vista escuela).
 */
class AmbitoSedeService
{
    /**
     * @return list<int>|null
     */
    public function idsPara(?User $user): ?array
    {
        if (! $user || ! $user->acotaPorSede()) {
            return null;
        }

        $ids = $user->sedeIdsOperativas();

        return $ids !== [] ? array_values(array_map('intval', $ids)) : [0];
    }

    public function etiqueta(?User $user): string
    {
        return $this->idsPara($user) !== null
            ? 'Indicadores de tus sedes'
            : 'Vista general de la escuela';
    }

    /**
     * @param  list<int>  $sedeIds
     */
    public function aplicarAlumnos(Builder $query, array $sedeIds): void
    {
        $ids = $sedeIds !== [] ? $sedeIds : [0];
        $query->where(function ($q) use ($ids) {
            $q->whereIn('sede_id', $ids)
                ->orWhereHas('bloques', fn ($b) => $b->whereIn('bloques.sede_id', $ids));
            if (Schema::hasColumn('alumnos', 'bloque_id')) {
                $q->orWhereHas('bloque', fn ($b) => $b->whereIn('sede_id', $ids));
            }
        });
    }

    /**
     * @param  list<int>  $sedeIds
     */
    public function aplicarBloques(Builder $query, array $sedeIds): void
    {
        $query->whereIn('sede_id', $sedeIds !== [] ? $sedeIds : [0]);
    }

    /**
     * Pagos cuyo detalle involucra alumnos de las sedes.
     *
     * @param  list<int>  $sedeIds
     */
    public function aplicarPagos(Builder $query, array $sedeIds): void
    {
        $query->whereHas('detalles.alumno', function ($q) use ($sedeIds) {
            $this->aplicarAlumnos($q, $sedeIds);
        });
    }

    /**
     * Cuotas que tocan las sedes (sede / bloque / general escolar).
     *
     * @param  list<int>  $sedeIds
     */
    public function aplicarCuotas(Builder $query, array $sedeIds): void
    {
        $ids = $sedeIds !== [] ? $sedeIds : [0];

        if (! Schema::hasColumn('cuotas', 'alcance')) {
            $query->where(function ($q) use ($ids) {
                $q->whereHas('bloque', fn ($b) => $b->whereIn('sede_id', $ids))
                    ->orWhereIn('sede_id', $ids);
            });

            return;
        }

        $query->where(function ($outer) use ($ids) {
            $outer
                ->where(function ($q) use ($ids) {
                    $q->where('alcance', Cuota::ALCANCE_SEDE)->whereIn('sede_id', $ids);
                })
                ->orWhere(function ($q) use ($ids) {
                    $q->where(function ($inner) {
                        $inner->where('alcance', Cuota::ALCANCE_BLOQUE)
                            ->orWhereNull('alcance')
                            ->orWhere('alcance', '');
                    })->whereHas('bloque', fn ($b) => $b->whereIn('sede_id', $ids));
                })
                ->orWhere('alcance', Cuota::ALCANCE_GENERAL);
        });
    }

    /**
     * @param  list<int>  $sedeIds
     */
    public function cuotaTocaSedes(Cuota $cuota, array $sedeIds): bool
    {
        $ids = $sedeIds !== [] ? $sedeIds : [0];
        $alcance = $cuota->getAttributes()['alcance'] ?? Cuota::ALCANCE_BLOQUE;
        if (! in_array($alcance, [Cuota::ALCANCE_BLOQUE, Cuota::ALCANCE_SEDE, Cuota::ALCANCE_GENERAL], true)) {
            $alcance = Cuota::ALCANCE_BLOQUE;
        }

        if ($alcance === Cuota::ALCANCE_GENERAL) {
            return true;
        }

        if ($alcance === Cuota::ALCANCE_SEDE) {
            return in_array((int) $cuota->sede_id, $ids, true);
        }

        $sid = (int) ($cuota->bloque?->sede_id ?? $cuota->sede_id ?? 0);

        return in_array($sid, $ids, true);
    }

    /**
     * @param  list<int>  $sedeIds
     */
    public function aplicarGastos(Builder $query, array $sedeIds): void
    {
        $query->whereIn('sede_id', $sedeIds !== [] ? $sedeIds : [0]);
    }

    /**
     * @param  list<int>  $sedeIds
     */
    public function aplicarEventos(Builder $query, array $sedeIds): void
    {
        $ids = $sedeIds !== [] ? $sedeIds : [0];
        $query->where(function ($q) use ($ids) {
            $q->whereIn('sede_id', $ids)
                ->orWhereHas('bloque', fn ($b) => $b->whereIn('sede_id', $ids));
        });
    }

    /**
     * @param  list<int>  $sedeIds
     */
    public function aplicarComprobantes(Builder $query, array $sedeIds): void
    {
        $ids = $sedeIds !== [] ? $sedeIds : [0];
        $query->where(function ($q) use ($ids) {
            $q->whereIn('sede_id', $ids)
                ->orWhereHas('items.bloque', fn ($b) => $b->whereIn('sede_id', $ids))
                ->orWhereHas('alumno', function ($a) use ($ids) {
                    $this->aplicarAlumnos($a, $ids);
                });
        });
    }

    /**
     * @param  list<int>  $sedeIds
     */
    public function aplicarSedesCatalogo(Builder $query, array $sedeIds): void
    {
        $query->whereIn('id', $sedeIds !== [] ? $sedeIds : [0]);
    }
}
