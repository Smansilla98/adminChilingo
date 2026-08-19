<?php

namespace App\Services;

use App\Models\Alumno;
use App\Models\Bloque;
use App\Models\ComprobanteCuotaAlumno;
use App\Models\ComprobanteCuotaAlumnoItem;
use App\Models\Cuota;
use App\Models\PagoDetalle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ComprobanteCuotaRegistroService
{
    /**
     * @param  list<int>  $bloqueIds
     */
    public function registrar(
        Alumno $alumno,
        int $sedeId,
        int $año,
        int $mes,
        string $fechaPago,
        array $bloqueIds,
        UploadedFile $archivo,
        ?string $notas,
        ?int $cargadoPorUserId = null,
    ): ComprobanteCuotaAlumno {
        $bloqueIds = array_values(array_unique(array_map('intval', $bloqueIds)));
        $itemsData = [];
        $montoTotal = 0.0;

        foreach ($bloqueIds as $bid) {
            $bloque = Bloque::query()->whereKey($bid)->where('sede_id', $sedeId)->where('activo', true)->first();
            if (! $bloque) {
                throw ValidationException::withMessages(['bloque_ids' => 'Uno de los bloques no corresponde a la sede elegida.']);
            }
            $cuota = Cuota::resolveForBloque($bid, $año, $mes);
            if (! $cuota) {
                throw ValidationException::withMessages(['bloque_ids' => 'No hay cuota para el bloque «'.$bloque->nombre.'» en el mes elegido.']);
            }
            if (! $cuota->aplicaAAlumno($alumno)) {
                throw ValidationException::withMessages(['alumno_id' => 'La cuota no aplica a este alumno en el bloque «'.$bloque->nombre.'».']);
            }
            $enBloque = $alumno->bloques()->where('bloques.id', $bid)->exists()
                || (int) $alumno->bloque_id === (int) $bid;
            if (! $enBloque) {
                throw ValidationException::withMessages(['alumno_id' => 'El alumno no está inscripto en todos los bloques seleccionados.']);
            }
            if ($this->alumnoYaPagoCuota((int) $alumno->id, (int) $cuota->id)) {
                throw ValidationException::withMessages([
                    'alumno_id' => 'Ya consta el pago de esta cuota para este alumno. No hace falta enviar otro comprobante.',
                ]);
            }
            $monto = (float) $cuota->monto;
            $montoTotal += $monto;
            $itemsData[] = [
                'cuota_id' => $cuota->id,
                'bloque_id' => $bid,
                'monto' => $monto,
            ];
        }

        $ext = strtolower((string) $archivo->getClientOriginalExtension());
        if (! in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
            $ext = strtolower((string) ($archivo->guessExtension() ?: 'pdf'));
        }
        if (! in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
            $ext = 'pdf';
        }
        $path = $archivo->storeAs('comprobantes_cuota_alumnos', (string) Str::uuid().'.'.$ext, 'comprobantes');

        return DB::transaction(function () use ($alumno, $sedeId, $fechaPago, $montoTotal, $path, $itemsData, $notas, $cargadoPorUserId) {
            $payload = [
                'alumno_id' => $alumno->id,
                'sede_id' => $sedeId,
                'fecha_pago' => $fechaPago,
                'monto_total' => round($montoTotal, 2),
                'comprobante_path' => $path,
                'notas' => $notas,
                'estado' => 'pendiente',
            ];
            if (Schema::hasColumn('comprobantes_cuota_alumnos', 'cargado_por_user_id')) {
                $payload['cargado_por_user_id'] = $cargadoPorUserId;
            }

            $c = ComprobanteCuotaAlumno::create($payload);
            foreach ($itemsData as $row) {
                ComprobanteCuotaAlumnoItem::create([
                    'comprobante_cuota_alumno_id' => $c->id,
                    'cuota_id' => $row['cuota_id'],
                    'bloque_id' => $row['bloque_id'],
                    'monto' => $row['monto'],
                ]);
            }

            return $c;
        });
    }

    public function alumnoYaPagoCuota(int $alumnoId, int $cuotaId): bool
    {
        if (! Schema::hasTable('pago_detalles')) {
            return false;
        }

        return PagoDetalle::query()
            ->where('alumno_id', $alumnoId)
            ->where('cuota_id', $cuotaId)
            ->exists();
    }
}
