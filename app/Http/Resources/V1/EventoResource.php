<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Evento */
class EventoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titulo' => $this->titulo,
            'descripcion' => $this->descripcion,
            'tipo' => $this->tipo_evento,
            'fecha' => $this->fecha?->toDateString(),
            'hora_inicio' => $this->hora_inicio?->format('H:i'),
            'hora_fin' => $this->hora_fin?->format('H:i'),
            'sede' => $this->whenLoaded('sede', fn () => $this->sede ? ['id' => $this->sede->id, 'nombre' => $this->sede->nombre] : null),
            'bloque' => $this->whenLoaded('bloque', fn () => $this->bloque ? ['id' => $this->bloque->id, 'nombre' => $this->bloque->nombre] : null),
        ];
    }
}
