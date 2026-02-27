<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bloque extends Model
{
    /** Tambores disponibles (igual que en Excel: Repique, Medio, Redoblante, Fondo Agudo, Fondo Grave, Timbal) */
    public const TAMBORES_DISPONIBLES = [
        'Repique',
        'Medio',
        'Redoblante',
        'Fondo Agudo',
        'Fondo Grave',
        'Timbal',
    ];

    protected $fillable = [
        'nombre',
        'año',
        'profesor_id',
        'corresponde_a',
        'sede_id',
        'cantidad_max_alumnos',
        'tambores',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'año' => 'integer',
        'cantidad_max_alumnos' => 'integer',
        'tambores' => 'array',
    ];

    /**
     * Relación con profesor
     */
    public function profesor(): BelongsTo
    {
        return $this->belongsTo(Profesor::class);
    }

    /**
     * Relación con sede
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /**
     * Alumnos inscritos en este bloque (un alumno puede estar en varios bloques)
     */
    public function alumnos(): BelongsToMany
    {
        return $this->belongsToMany(Alumno::class, 'alumno_bloque')
            ->withPivot('es_principal')
            ->withTimestamps();
    }

    /**
     * Horarios semanales del bloque (día + hora)
     */
    public function horarios(): HasMany
    {
        return $this->hasMany(BloqueHorario::class, 'bloque_id');
    }

    /**
     * Shows en los que participa este bloque
     */
    public function shows(): BelongsToMany
    {
        return $this->belongsToMany(Show::class, 'show_bloque')->withTimestamps();
    }

    /**
     * Relación con eventos
     */
    public function eventos(): HasMany
    {
        return $this->hasMany(Evento::class);
    }

    /**
     * Relación con asistencias
     */
    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class);
    }

    /**
     * Obtener cantidad de alumnos inscritos (activos) en este bloque
     */
    public function getCantidadAlumnosAttribute(): int
    {
        return $this->alumnos()->where('alumnos.activo', true)->count();
    }

    /**
     * Verificar si tiene cupos disponibles
     */
    public function tieneCuposDisponibles(): bool
    {
        return $this->cantidad_alumnos < $this->cantidad_max_alumnos;
    }
}
