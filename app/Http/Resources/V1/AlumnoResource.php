<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Datos mínimos para listas (asistencia, alumnos). El DNI y la fecha de nacimiento
 * solo se incluyen en el detalle y si quien consulta puede editar al alumno.
 *
 * @mixin \App\Models\Alumno
 */
class AlumnoResource extends JsonResource
{
    public bool $detalle = false;

    public function toArray(Request $request): array
    {
        $puedeVerSensibles = $this->detalle && $request->user()?->can('update', $this->resource);

        return [
            'id' => $this->id,
            'persona_id' => $this->persona_id,
            'nombre' => $this->nombre_apellido,
            'activo' => (bool) $this->activo,
            'instrumento' => $this->instrumento_principal,
            'sede' => $this->whenLoaded('sede', fn () => $this->sede ? ['id' => $this->sede->id, 'nombre' => $this->sede->nombre] : null),
            'bloques' => $this->whenLoaded('bloques', fn () => $this->bloques->map(fn ($b) => ['id' => $b->id, 'nombre' => $b->nombre])->values()),
            'telefono' => $this->when($this->detalle, $this->telefono),
            'dni' => $this->when($puedeVerSensibles, $this->dni),
            'fecha_nacimiento' => $this->when($puedeVerSensibles, fn () => $this->fecha_nacimiento?->toDateString()),
        ];
    }
}
