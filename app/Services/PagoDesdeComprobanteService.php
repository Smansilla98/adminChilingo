<?php

namespace App\Services;

use App\Models\Alumno;
use App\Models\ComprobanteCuotaAlumno;
use App\Models\ComprobanteCuotaAlumnoItem;
use App\Models\Cuota;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Support\LiquidacionDocente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class PagoDesdeComprobanteService
{
    /**
     * Crea un Pago a partir del comprobante del alumno y lo marca como pagado.
     *
     * @return array{pago: Pago, omitidas: int, mensaje: string}
     */
    public function aprobar(ComprobanteCuotaAlumno $comprobante, ?int $userId, bool $liquidarProfesor = true): array
    {
        if (! Schema::hasTable('pagos') || ! Schema::hasTable('pago_detalles')) {
            throw new RuntimeException('Faltan tablas de pagos. Ejecutá las migraciones.');
        }

        $comprobante->loadMissing(['items.cuota.bloque.sede', 'items.cuota.sede', 'alumno', 'items.bloque']);

        if ($comprobante->pago_id) {
            throw new RuntimeException('Este comprobante ya tiene un pago asociado.');
        }

        $alumnoId = (int) $comprobante->alumno_id;
        $this->engancharPagosExtra($comprobante);
        $lineas = [];
        $omitidas = 0;

        foreach ($comprobante->items as $item) {
            $cuotaId = (int) $item->cuota_id;
            if ($cuotaId <= 0 || ! $item->cuota) {
                $omitidas++;

                continue;
            }

            $yaPagado = PagoDetalle::query()
                ->where('alumno_id', $alumnoId)
                ->where('cuota_id', $cuotaId)
                ->exists();

            if ($yaPagado) {
                $omitidas++;

                continue;
            }

            $alumno = $comprobante->alumno ?? Alumno::query()->find($alumnoId);
            if (! $alumno || ! $item->cuota->aplicaAAlumno($alumno)) {
                $omitidas++;

                continue;
            }

            $lineas[] = [
                'alumno_id' => $alumnoId,
                'cuota_id' => $cuotaId,
                'monto' => round((float) $item->monto, 2),
            ];
        }

        if ($lineas === []) {
            throw new RuntimeException(
                $omitidas > 0
                    ? 'No se pudo crear el pago: todas las cuotas ya estaban cobradas o no aplican al alumno.'
                    : 'El comprobante no tiene líneas de cuota válidas.'
            );
        }

        $montoTotal = round(array_sum(array_column($lineas, 'monto')), 2);
        $cuotasPorId = Cuota::query()
            ->with(['bloque.sede', 'sede'])
            ->whereIn('id', array_column($lineas, 'cuota_id'))
            ->get()
            ->keyBy('id');

        $path = $this->resolverPathComprobante($comprobante);

        $notas = trim((string) ($comprobante->notas ?? ''));
        $notaSistema = 'Generado desde comprobante #'.$comprobante->id;
        $notasFinal = $notas !== '' ? $notaSistema.' — '.$notas : $notaSistema;

        $pago = DB::transaction(function () use (
            $comprobante,
            $lineas,
            $cuotasPorId,
            $montoTotal,
            $path,
            $notasFinal,
            $userId,
            $liquidarProfesor
        ) {
            $pago = Pago::create([
                'fecha_pago' => $comprobante->fecha_pago?->format('Y-m-d') ?? now()->toDateString(),
                'monto_total' => $montoTotal,
                'comprobante_path' => $path,
                'notas' => $notasFinal,
                'registrado_por' => $userId,
            ]);

            $abonos = $liquidarProfesor && class_exists(LiquidacionDocente::class)
                ? LiquidacionDocente::abonosPorLinea($lineas, $cuotasPorId)
                : [];

            foreach ($lineas as $idx => $linea) {
                $cuota = $cuotasPorId->get((int) $linea['cuota_id']);
                $det = [
                    'pago_id' => $pago->id,
                    'alumno_id' => (int) $linea['alumno_id'],
                    'cuota_id' => (int) $linea['cuota_id'],
                    'monto' => (float) $linea['monto'],
                ];

                if ($liquidarProfesor && Schema::hasColumn('pago_detalles', 'abono_profesor')) {
                    $abono = round((float) ($abonos[$idx] ?? 0), 2);
                    $cuotaRef = (float) ($cuota->monto ?? 0);
                    $det['abono_profesor'] = $abono;
                    $det['abono_base'] = $cuotaRef;
                    $det['abono_porcentaje'] = $cuotaRef > 0 ? round(100 * $abono / $cuotaRef, 4) : null;
                    $det['abono_nota'] = 'Abono desde comprobante de alumno #'.$comprobante->id;
                }

                PagoDetalle::create($det);
            }

            $update = ['estado' => 'pagado'];
            if (Schema::hasColumn('comprobantes_cuota_alumnos', 'pago_id')) {
                $update['pago_id'] = $pago->id;
            }
            $comprobante->update($update);

            return $pago;
        });

        $mensaje = 'Pago #'.$pago->id.' registrado y comprobante marcado como pagado.';
        if ($omitidas > 0) {
            $mensaje .= " Se omitieron {$omitidas} línea(s) ya cobradas o inválidas.";
        }

        return [
            'pago' => $pago,
            'omitidas' => $omitidas,
            'mensaje' => $mensaje,
        ];
    }

    /**
     * Pagos extra (sin cuota emitida al pagar): busca la cuota de ese bloque y mes. Si alguna
     * todavía no existe, no aprueba nada: hay que emitirla primero para que el pago quede
     * en su cuota y el docente cobre lo que le corresponde.
     */
    private function engancharPagosExtra(ComprobanteCuotaAlumno $comprobante): void
    {
        $faltan = [];
        foreach ($comprobante->items as $item) {
            if (! $item instanceof ComprobanteCuotaAlumnoItem || $item->cuota_id || ! $item->bloque_id || ! $item->anio || ! $item->mes) {
                continue;
            }
            $cuota = Cuota::resolveForBloque((int) $item->bloque_id, (int) $item->anio, (int) $item->mes);
            if (! $cuota) {
                $faltan[] = sprintf('%s · %02d/%d', $item->bloque->nombre ?? 'bloque', $item->mes, $item->anio);

                continue;
            }
            $item->update(['cuota_id' => $cuota->id]);
            $item->setRelation('cuota', $cuota->loadMissing(['bloque.sede', 'sede']));
        }
        if ($faltan !== []) {
            throw new RuntimeException('Falta emitir la cuota de '.implode(', ', $faltan).'. Creala en Cuotas y volvé a aprobar.');
        }
    }

    private function resolverPathComprobante(ComprobanteCuotaAlumno $comprobante): ?string
    {
        $origen = $comprobante->comprobante_path;
        if (! $origen) {
            return null;
        }

        $disk = Storage::disk('comprobantes');
        if (! $disk->exists($origen)) {
            return $origen;
        }

        // Si ya está bajo pagos/, reutilizar
        if (str_starts_with($origen, 'pagos/')) {
            return $origen;
        }

        $ext = strtolower((string) pathinfo($origen, PATHINFO_EXTENSION)) ?: 'pdf';
        if (! in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
            $ext = 'pdf';
        }
        $destino = 'pagos/'.(string) Str::uuid().'.'.$ext;

        try {
            $disk->copy($origen, $destino);

            return $destino;
        } catch (\Throwable $e) {
            return $origen;
        }
    }
}
