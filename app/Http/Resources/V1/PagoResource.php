<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Pago */
class PagoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fecha' => $this->fecha_pago?->toDateString(),
            'monto_total' => (float) $this->monto_total,
            'anulado' => $this->anulado_at !== null,
            'motivo_anulacion' => $this->motivo_anulacion,
            'notas' => $this->notas,
            'detalles' => $this->whenLoaded('detalles', fn () => $this->detalles->map(fn ($d) => [
                'alumno_id' => $d->alumno_id,
                'alumno' => $d->alumno?->nombre_apellido,
                'cuota_id' => $d->cuota_id,
                'cuota' => $d->cuota?->nombre,
                'monto' => (float) $d->monto,
            ])->values()),
            'registrado_por' => $this->whenLoaded('registradoPor', fn () => $this->registradoPor?->name),
        ];
    }
}
