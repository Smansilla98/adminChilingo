<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Profesor extends Model
{
    protected $fillable = [
        'user_id',
        'nombre',
        'telefono',
        'email',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * Usuario asociado (un profesor puede ser también alumno en otro bloque)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Bloques donde este profesor da clase (un profesor puede tener varios bloques)
     */
    public function bloques(): HasMany
    {
        return $this->hasMany(Bloque::class);
    }

    /**
     * Sede de la que es coordinador (si aplica)
     */
    public function sedeCoordinada(): HasOne
    {
        return $this->hasOne(Sede::class, 'coordinador_id');
    }

    /**
     * Áreas que coordina: género, costa, tambores (coordinador de área)
     */
    public function coordinadorAreas(): HasMany
    {
        return $this->hasMany(CoordinadorArea::class);
    }

    /**
     * Relación con eventos
     */
    public function eventos(): HasMany
    {
        return $this->hasMany(Evento::class);
    }

    /**
     * Obtener bloques activos
     */
    public function bloquesActivos(): HasMany
    {
        return $this->bloques()->where('activo', true);
    }
}
