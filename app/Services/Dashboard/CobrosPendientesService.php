<?php

namespace App\Services\Dashboard;

use App\Models\Alumno;
use App\Models\Cuota;
use App\Models\PagoDetalle;
use App\Services\AmbitoSedeService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Cobros pendientes del mes: qué alumnos deben cada cuota y cuáles no pagaron.
 */
class CobrosPendientesService
{
    /**
     * Listado lateral de cobros pendientes (alumno + cuota del mes sin línea en pago_detalles).
     *
     * @param  \Illuminate\Support\Collection<int, Cuota>  $cuotasMes
     * @param  list<int>|null  $filtroSedes
     */
    public function armarListadoCobrosPendientes(
        $cuotasMes,
        Carbon $hoy,
        int $limit = 8,
        ?array $filtroSedes = null,
        ?AmbitoSedeService $ambito = null
    ): \Illuminate\Support\Collection {
        if ($cuotasMes->isEmpty() || ! Schema::hasTable('pago_detalles')) {
            return collect();
        }

        $cuotaIds = $cuotasMes->pluck('id')->filter()->values();
        $pagados = PagoDetalle::query()
            ->whereIn('cuota_id', $cuotaIds)
            ->get(['alumno_id', 'cuota_id'])
            ->mapWithKeys(fn (PagoDetalle $pd) => [$pd->alumno_id.'-'.$pd->cuota_id => true]);

        $mesLabel = now()->locale('es')->translatedFormat('M Y');
        $filas = collect();

        foreach ($cuotasMes as $cuota) {
            foreach ($this->alumnosObjetivoCuota($cuota, $filtroSedes, $ambito) as $alumno) {
                if ($pagados->has($alumno->id.'-'.$cuota->id)) {
                    continue;
                }

                $fv = $cuota->fecha_vencimiento;
                $isVencida = $fv ? $fv->copy()->startOfDay()->lt($hoy->copy()->subDays(5)) : false;

                $filas->push([
                    'alumno' => $alumno->nombre_apellido,
                    'sede' => $alumno->sede->nombre
                        ?? $cuota->sede->nombre
                        ?? $cuota->bloque->sede->nombre
                        ?? '—',
                    'monto' => (float) $cuota->monto,
                    'cuota_nombre' => $cuota->nombre,
                    'dot_class' => $isVencida ? 'dot-danger' : '',
                    'mes_label' => $mesLabel,
                    '_sort_venc' => $fv ? $fv->timestamp : 0,
                    '_sort_created' => $cuota->created_at->timestamp ?? 0,
                ]);
            }
        }

        return $filas
            ->sort(function (array $a, array $b) {
                if ($a['_sort_venc'] !== $b['_sort_venc']) {
                    return $b['_sort_venc'] <=> $a['_sort_venc'];
                }

                return $b['_sort_created'] <=> $a['_sort_created'];
            })
            ->take($limit)
            ->map(fn (array $row) => collect($row)->except(['_sort_venc', '_sort_created'])->all())
            ->values();
    }

    /**
     * @param  list<int>|null  $filtroSedes
     */
    public function contarObjetivoCuota(Cuota $c, ?array $filtroSedes, AmbitoSedeService $ambito): int
    {
        return $this->alumnosObjetivoCuota($c, $filtroSedes, $ambito)->unique('id')->count();
    }

    /**
     * Alumnos a los que aplica una cuota del mes (lista explícita en cuota_alumno o por alcance).
     *
     * @param  list<int>|null  $filtroSedes
     * @return \Illuminate\Support\Collection<int, Alumno>
     */
    public function alumnosObjetivoCuota(Cuota $cuota, ?array $filtroSedes = null, ?AmbitoSedeService $ambito = null): \Illuminate\Support\Collection
    {
        if ($cuota->relationLoaded('alumnos') && $cuota->alumnos->isNotEmpty()) {
            $alumnos = $cuota->alumnos
                ->where('activo', true)
                ->loadMissing('sede')
                ->unique('id')
                ->values();
            if ($filtroSedes !== null && $ambito) {
                $ids = $filtroSedes !== [] ? $filtroSedes : [0];
                $alumnos = $alumnos->filter(function (Alumno $a) use ($ids) {
                    if (in_array((int) $a->sede_id, $ids, true)) {
                        return true;
                    }
                    $a->loadMissing('bloques');

                    return $a->bloques->contains(fn ($b) => in_array((int) $b->sede_id, $ids, true));
                })->values();
            }

            return $alumnos;
        }

        $alcance = Schema::hasColumn('cuotas', 'alcance')
            ? $cuota->alcanceNormalizado()
            : Cuota::ALCANCE_BLOQUE;

        if ($alcance === Cuota::ALCANCE_GENERAL) {
            $q = Alumno::query()
                ->where('activo', true)
                ->where(function ($q) {
                    $q->whereHas('bloques')->orWhereNotNull('bloque_id');
                })
                ->with('sede')
                ->orderBy('nombre_apellido');
            if ($filtroSedes !== null && $ambito) {
                $ambito->aplicarAlumnos($q, $filtroSedes);
            }

            return $q->get();
        }

        if ($alcance === Cuota::ALCANCE_SEDE && $cuota->sede_id) {
            $sid = (int) $cuota->sede_id;
            if ($filtroSedes !== null && ! in_array($sid, $filtroSedes, true)) {
                return collect();
            }

            return Alumno::query()
                ->where('activo', true)
                ->where(function ($q) use ($sid) {
                    $q->whereHas('bloques', fn ($b) => $b->where('bloques.sede_id', $sid))
                        ->orWhere('sede_id', $sid);
                })
                ->with('sede')
                ->orderBy('nombre_apellido')
                ->get();
        }

        if (! $cuota->bloque_id) {
            return collect();
        }

        $bid = (int) $cuota->bloque_id;
        if ($filtroSedes !== null) {
            $bloqueSede = (int) ($cuota->bloque->sede_id ?? 0);
            if ($bloqueSede && ! in_array($bloqueSede, $filtroSedes, true)) {
                return collect();
            }
        }

        return Alumno::query()
            ->where('activo', true)
            ->where(function ($q) use ($bid) {
                if (Schema::hasTable('alumno_bloque')) {
                    $q->whereHas('bloques', fn ($b) => $b->where('bloques.id', $bid))
                        ->orWhere('bloque_id', $bid);
                } else {
                    $q->where('bloque_id', $bid);
                }
            })
            ->with('sede')
            ->orderBy('nombre_apellido')
            ->get();
    }
}
