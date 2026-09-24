<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Persona */
class PersonaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'apellido' => $this->apellido,
            'nombre_completo' => $this->nombre_completo,
            'dni' => $this->dni,
            'fecha_nacimiento' => $this->fecha_nacimiento?->toDateString(),
            'telefono' => $this->telefono,
            'email' => $this->email,
            'estado' => $this->estado,
            'tiene_cuenta' => $this->whenLoaded('user', fn () => $this->user !== null),
            'es_alumno' => $this->whenLoaded('alumnos', fn () => $this->alumnos->isNotEmpty()),
            'es_docente' => $this->whenLoaded('profesor', fn () => $this->profesor !== null),
        ];
    }
}
