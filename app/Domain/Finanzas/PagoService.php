<?php

namespace App\Domain\Finanzas;

use App\Models\Auditoria;
use App\Models\ComprobanteCuotaAlumno;
use App\Models\Pago;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PagoService
{
    /**
     * Anula un pago: deja de contar para saldos y reportes, pero queda en el historial.
     * Si provenía de un comprobante del alumno, el comprobante vuelve a "pendiente".
     */
    public function anular(Pago $pago, User $por, string $motivo): Pago
    {
        if ($pago->estaAnulado()) {
            throw ValidationException::withMessages(['pago' => 'El pago ya estaba anulado.']);
        }
        $motivo = trim($motivo);
        if (mb_strlen($motivo) < 5) {
            throw ValidationException::withMessages(['motivo' => 'Indicá el motivo de la anulación (mínimo 5 caracteres).']);
        }

        return DB::transaction(function () use ($pago, $por, $motivo) {
            $pago->forceFill([
                'anulado_at' => now(),
                'anulado_por' => $por->id,
                'motivo_anulacion' => $motivo,
            ])->save();

            if (Schema::hasColumn('comprobantes_cuota_alumnos', 'pago_id')) {
                ComprobanteCuotaAlumno::query()->where('pago_id', $pago->id)
                    ->update(['pago_id' => null, 'estado' => 'pendiente']);
            }

            Auditoria::registrar('anulado', $pago, null, ['motivo' => $motivo, 'monto_total' => (string) $pago->monto_total]);

            return $pago->fresh();
        });
    }
}
