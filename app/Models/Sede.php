<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sede extends Model
{
    protected $fillable = [
        'nombre',
        'direccion',
        'tipo_propiedad',
        'costo_alquiler_mensual',
        'coordinador_id',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * Profesor coordinador de esta sede (ej: Banfield)
     */
    public function coordinador(): BelongsTo
    {
        return $this->belongsTo(Profesor::class, 'coordinador_id');
    }

    /**
     * Relación con bloques
     */
    public function bloques(): HasMany
    {
        return $this->hasMany(Bloque::class);
    }

    /**
     * Alumnos que tienen esta sede como principal (sede_id)
     */
    public function alumnos(): HasMany
    {
        return $this->hasMany(Alumno::class);
    }

    /**
     * Relación con eventos
     */
    public function eventos(): HasMany
    {
        return $this->hasMany(Evento::class);
    }

    /**
     * Inventario asignado a esta sede
     */
    public function inventarioItems(): HasMany
    {
        return $this->hasMany(InventarioItem::class);
    }

    public function gastos(): HasMany
    {
        return $this->hasMany(Gasto::class);
    }

    /**
     * Obtener alumnos activos
     */
    public function alumnosActivos(): HasMany
    {
        return $this->alumnos()->where('activo', true);
    }
}
