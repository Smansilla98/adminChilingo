<?php

namespace App\Domain\Finanzas;

use App\Models\Alumno;
use App\Models\Beca;
use App\Models\Cuota;
use App\Models\PagoDetalle;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Estado de cuenta de un alumno: qué cuotas le corresponden, qué descuento de beca
 * aplica, cuánto pagó y cuánto debe. No todos pagan lo mismo: cada cuota respeta su
 * alcance (bloque / sede / general / lista de alumnos) y cada beca su vigencia.
 */
class EstadoCuentaService
{
    /**
     * @return array{
     *   alumno_id: int, anio: int,
     *   items: list<array<string, mixed>>,
     *   totales: array{bruto: float, descuento: float, neto: float, pagado: float, saldo: float, vencido: float},
     *   becas: list<array<string, mixed>>
     * }
     */
    public function paraAlumno(Alumno $alumno, ?int $anio = null): array
    {
        $anio ??= (int) now()->year;
        $alumno->loadMissing(['bloques:id,sede_id', 'bloque:id,sede_id']);

        $bloques = $alumno->bloques->pluck('sede_id', 'id')->all();
        if ($alumno->bloque) {
            $bloques[$alumno->bloque->id] = $alumno->bloque->sede_id;
        }
        $bloqueIds = array_map('intval', array_keys($bloques));
        $sedeIds = array_values(array_unique(array_filter(array_merge(array_values($bloques), [$alumno->sede_id]))));

        $cuotas = $this->cuotasAplicables($alumno, $anio, $bloqueIds, $sedeIds);
        $pagado = PagoDetalle::query()
            ->where('alumno_id', $alumno->id)
            ->whereIn('cuota_id', $cuotas->pluck('id')->all() ?: [0])
            ->selectRaw('cuota_id, SUM(monto) as total, MAX(pago_id) as ultimo_pago_id')
            ->groupBy('cuota_id')
            ->get()
            ->keyBy('cuota_id');
        $becas = $alumno->becas()->where('estado', '!=', 'finalizada')->orderByDesc('fecha_inicio')->get();

        $hoy = Carbon::today();
        $items = [];
        $totales = ['bruto' => 0.0, 'descuento' => 0.0, 'neto' => 0.0, 'pagado' => 0.0, 'saldo' => 0.0, 'vencido' => 0.0];

        foreach ($cuotas as $cuota) {
            $fechaRef = $cuota->fecha_vencimiento ?? Carbon::create($cuota->año, max(1, (int) $cuota->mes), 1);
            $sedeCuota = $cuota->sede_id ?? $cuota->bloque?->sede_id;
            $beca = $becas->first(fn (Beca $b) => $b->estado === 'activa'
                && $b->fecha_inicio->lte($fechaRef)
                && (! $b->fecha_fin || $b->fecha_fin->gte($fechaRef))
                && $b->aplicaA($cuota->bloque_id, $sedeCuota));

            $bruto = round((float) $cuota->monto, 2);
            $descuento = $beca ? $beca->descuentoSobre($bruto) : 0.0;
            $neto = round($bruto - $descuento, 2);
            $montoPagado = round((float) ($pagado->get($cuota->id)?->total ?? 0), 2);
            $saldo = round(max(0, $neto - $montoPagado), 2);
            $vencida = $cuota->fecha_vencimiento && $cuota->fecha_vencimiento->lt($hoy);

            $estado = match (true) {
                $neto <= 0 => 'becada',
                $montoPagado >= $neto => 'pagada',
                $montoPagado > 0 => 'parcial',
                $vencida => 'vencida',
                default => 'pendiente',
            };

            $items[] = [
                'cuota_id' => $cuota->id,
                'nombre' => $cuota->nombre,
                'anio' => (int) $cuota->año,
                'mes' => $cuota->mes ? (int) $cuota->mes : null,
                'periodo' => $cuota->mes ? sprintf('%02d/%d', $cuota->mes, $cuota->año) : (string) $cuota->año,
                'vencimiento' => $cuota->fecha_vencimiento?->toDateString(),
                'bruto' => $bruto,
                'beca' => $beca ? ['id' => $beca->id, 'etiqueta' => $beca->etiqueta()] : null,
                'descuento' => $descuento,
                'neto' => $neto,
                'pagado' => $montoPagado,
                'saldo' => $saldo,
                'estado' => $estado,
                'ultimo_pago_id' => $pagado->get($cuota->id)?->ultimo_pago_id,
            ];

            $totales['bruto'] += $bruto;
            $totales['descuento'] += $descuento;
            $totales['neto'] += $neto;
            $totales['pagado'] += $montoPagado;
            $totales['saldo'] += $saldo;
            if ($vencida) {
                $totales['vencido'] += $saldo;
            }
        }

        return [
            'alumno_id' => (int) $alumno->id,
            'anio' => $anio,
            'items' => $items,
            'totales' => array_map(fn ($v) => round($v, 2), $totales),
            'becas' => $becas->map(fn (Beca $b) => [
                'id' => $b->id,
                'etiqueta' => $b->etiqueta(),
                'tipo' => $b->tipo,
                'estado' => $b->estado,
                'desde' => $b->fecha_inicio->toDateString(),
                'hasta' => $b->fecha_fin?->toDateString(),
                'motivo' => $b->motivo,
            ])->values()->all(),
        ];
    }

    /**
     * Misma regla que Cuota::aplicaAAlumno, resuelta en memoria para no consultar por cuota.
     *
     * @param  list<int>  $bloqueIds
     * @param  list<int>  $sedeIds
     * @return Collection<int, Cuota>
     */
    private function cuotasAplicables(Alumno $alumno, int $anio, array $bloqueIds, array $sedeIds): Collection
    {
        return Cuota::query()
            ->with(['alumnos:id', 'bloque:id,sede_id'])
            ->where('año', $anio)
            ->where('activo', true)
            ->where(function ($q) use ($bloqueIds, $sedeIds) {
                $q->where('alcance', Cuota::ALCANCE_GENERAL)
                    ->orWhereIn('bloque_id', $bloqueIds ?: [0])
                    ->orWhere(fn ($s) => $s->where('alcance', Cuota::ALCANCE_SEDE)->whereIn('sede_id', $sedeIds ?: [0]));
            })
            ->orderBy('mes')
            ->orderBy('id')
            ->get()
            ->filter(function (Cuota $cuota) use ($alumno, $bloqueIds, $sedeIds) {
                $ok = match ($cuota->alcanceNormalizado()) {
                    Cuota::ALCANCE_BLOQUE => $cuota->bloque_id && in_array((int) $cuota->bloque_id, $bloqueIds, true),
                    Cuota::ALCANCE_SEDE => $cuota->sede_id && in_array((int) $cuota->sede_id, array_map('intval', $sedeIds), true),
                    default => $bloqueIds !== [],
                };
                if (! $ok) {
                    return false;
                }

                return $cuota->alumnos->isEmpty() || $cuota->alumnos->contains('id', $alumno->id);
            })
            ->values();
    }
}
