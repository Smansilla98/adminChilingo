<?php

namespace App\Http\Resources\V1;

use App\Models\InventarioItem;
use App\Models\InventarioMovimiento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\InventarioItem */
class InventarioItemResource extends JsonResource
{
    private function prestamoAbierto(): ?\App\Models\InventarioPrestamo
    {
        if ($this->resource->relationLoaded('prestamos')) {
            return $this->resource->prestamos->whereNull('devuelto_el')->first();
        }

        return \App\Models\InventarioPrestamo::query()->where('inventario_item_id', $this->id)->whereNull('devuelto_el')->first();
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'tipo' => $this->tipo,
            'tipo_nombre' => $this->tipo_label,
            'estado' => $this->estado,
            'estado_nombre' => InventarioItem::ESTADOS[$this->estado] ?? $this->estado,
            'marca' => $this->marca,
            'modelo' => $this->modelo,
            'medida' => $this->medida,
            'cantidad' => (float) $this->cantidad,
            'es_consumible' => (bool) $this->es_consumible,
            'propietario' => $this->propietario_tipo,
            'propietario_alumno' => $this->whenLoaded('alumno', fn () => $this->alumno ? ['id' => $this->alumno->id, 'nombre' => $this->alumno->nombre_apellido] : null),
            'sede' => $this->whenLoaded('sede', fn () => $this->sede ? ['id' => $this->sede->id, 'nombre' => $this->sede->nombre] : null),
            'notas' => $this->notas,
            'ficha_publica_url' => $this->codigo ? route('inventario.publico', $this->codigo) : null,
            'movimientos' => $this->whenLoaded('movimientos', fn () => $this->movimientos->take(20)->map(fn (InventarioMovimiento $m) => [
                'id' => $m->id,
                'tipo' => $m->tipo,
                'tipo_nombre' => $m->etiquetaTipo(),
                'nota' => $m->nota,
                'sede' => $m->sede?->nombre,
                'autor' => $m->autor?->name,
                'fecha' => $m->created_at?->toIso8601String(),
            ])->values()),
            // Con un préstamo abierto el instrumento no figura disponible.
            'prestado' => $this->prestamoAbierto() !== null,
            'prestamo' => $this->prestamoAbierto()?->responsable(),
            'puede_editar' => $request->user()?->can('update', $this->resource) ?? false,
            'puede_eliminar' => $request->user()?->can('delete', $this->resource) ?? false,
        ];
    }
}
