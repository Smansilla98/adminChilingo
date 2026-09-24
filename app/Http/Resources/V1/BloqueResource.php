<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Bloque */
class BloqueResource extends JsonResource
{
    public const DIAS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'anio' => (int) $this->año,
            'sede' => $this->whenLoaded('sede', fn () => $this->sede ? ['id' => $this->sede->id, 'nombre' => $this->sede->nombre] : null),
            'activo' => (bool) $this->activo,
            'cantidad_alumnos' => $this->whenCounted('alumnos'),
            'horarios' => $this->whenLoaded('horarios', fn () => $this->horarios->map(fn ($h) => [
                'dia' => (int) $h->dia_semana,
                'dia_nombre' => self::DIAS[(int) $h->dia_semana] ?? null,
                'inicio' => $h->hora_inicio?->format('H:i'),
                'fin' => $h->hora_fin?->format('H:i'),
            ])->values()),
            'mi_rol' => $this->when(isset($this->mi_rol), fn () => $this->mi_rol),
        ];
    }
}
