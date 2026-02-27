<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Alumno extends Model
{
    protected $fillable = [
        'user_id',
        'nombre_apellido',
        'dni',
        'fecha_nacimiento',
        'telefono',
        'instrumento_principal',
        'instrumento_secundario',
        'tipo_tambor',
        'bloque_id',
        'sede_id',
        'activo',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'activo' => 'boolean',
    ];

    /**
     * Usuario asociado (un alumno puede ser también profesor en otros bloques)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Bloques a los que pertenece el alumno (un alumno puede estar en varios bloques)
     */
    public function bloques(): BelongsToMany
    {
        return $this->belongsToMany(Bloque::class, 'alumno_bloque')
            ->withPivot('es_principal')
            ->withTimestamps();
    }

    /**
     * Bloque principal (compatibilidad: el primero con es_principal o el de bloque_id)
     */
    public function bloque(): BelongsTo
    {
        return $this->belongsTo(Bloque::class);
    }

    /**
     * Relación con sede
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /**
     * Relación con asistencias
     */
    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class);
    }

    public function inventarioItems(): HasMany
    {
        return $this->hasMany(InventarioItem::class);
    }

    /**
     * Calcular edad
     */
    public function getEdadAttribute(): int
    {
        return Carbon::parse($this->fecha_nacimiento)->age;
    }

    /**
     * Obtener asistencias de un bloque específico
     */
    public function asistenciasPorBloque(int $bloqueId): HasMany
    {
        return $this->asistencias()->where('bloque_id', $bloqueId);
    }
}
