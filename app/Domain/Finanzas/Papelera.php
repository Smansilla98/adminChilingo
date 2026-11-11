<?php

namespace App\Domain\Finanzas;

use App\Models\Auditoria;
use App\Models\Gasto;
use App\Models\User;
use App\Models\VillaGesellGasto;
use Illuminate\Support\Facades\DB;

/**
 * Papelera de gastos (de la escuela y de la gira): borrar deja el registro oculto, con
 * quién lo borró y por qué; los listados y reportes ya no lo cuentan. Se puede restaurar.
 * (Los pagos no se borran: se anulan, con motivo y usuario, y quedan en el historial.)
 */
class Papelera
{
    public function eliminar(Gasto|VillaGesellGasto $registro, User $por, ?string $motivo = null): void
    {
        $motivo = trim((string) $motivo) !== '' ? mb_substr(trim((string) $motivo), 0, 500) : null;
        DB::transaction(function () use ($registro, $por, $motivo) {
            $registro->forceFill(['eliminado_por' => $por->id, 'motivo_eliminacion' => $motivo])->saveQuietly();
            $registro->delete();
            if ($registro instanceof VillaGesellGasto) {
                // Gasto ya se audita solo (Auditable); el de la gira, a mano.
                Auditoria::registrar('deleted', $registro, $registro->only(['tipo', 'concepto', 'monto', 'modo']), ['motivo' => $motivo]);
            }
        });
    }

    public function restaurar(Gasto|VillaGesellGasto $registro): void
    {
        DB::transaction(function () use ($registro) {
            $antes = ['eliminado_por' => $registro->eliminado_por, 'motivo_eliminacion' => $registro->motivo_eliminacion];
            $registro->forceFill(['eliminado_por' => null, 'motivo_eliminacion' => null])->saveQuietly();
            $registro->restore();
            Auditoria::registrar('restaurado', $registro, $antes, null);
        });
    }
}
