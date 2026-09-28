<?php

namespace App\Domain\Finanzas;

use App\Models\Alumno;
use App\Models\Beca;
use App\Models\User;

/**
 * Otorgamiento y cambios de becas. La beca se aplica al estado de cuenta desde su
 * fecha de inicio (EstadoCuentaService); la auditoría la registra el modelo.
 */
class BecaService
{
    /**
     * @param  array<string, mixed>  $datos  validados por BecaRequest
     */
    public function otorgar(Alumno $alumno, array $datos, User $por): Beca
    {
        unset($datos['alumno_id']);
        if ($datos['tipo'] !== 'porcentaje') {
            $datos['porcentaje'] = null;
        }
        if ($datos['tipo'] !== 'monto_fijo') {
            $datos['monto'] = null;
        }

        return $alumno->becas()->create($datos + ['otorgada_por' => $por->id, 'estado' => 'activa']);
    }

    /**
     * @param  array<string, mixed>  $datos  estado, fecha_fin, observaciones
     */
    public function actualizar(Beca $beca, array $datos): Beca
    {
        $beca->update($datos);

        return $beca;
    }
}
