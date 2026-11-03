<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evento extends Model
{
    protected $fillable = [
        'titulo',
        'descripcion',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'sede_id',
        'tipo_evento',
        'profesor_id',
        'bloque_id',
        'cantidad_personas',
        'created_by',
    ];

    protected $casts = [
        'fecha' => 'date',
        'hora_inicio' => 'datetime',
        'hora_fin' => 'datetime',
        'cantidad_personas' => 'integer',
    ];

    /**
     * Relación con sede
     *
     * @return BelongsTo<Sede, $this>
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /**
     * Relación con profesor
     *
     * @return BelongsTo<Profesor, $this>
     */
    public function profesor(): BelongsTo
    {
        return $this->belongsTo(Profesor::class);
    }

    /**
     * Relación con bloque
     *
     * @return BelongsTo<Bloque, $this>
     */
    public function bloque(): BelongsTo
    {
        return $this->belongsTo(Bloque::class);
    }

    /**
     * Relación con usuario creador
     *
     * @return BelongsTo<User, $this>
     */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope para eventos próximos
     */
    public function scopeProximos($query)
    {
        return $query->where('fecha', '>=', now()->toDateString())
            ->orderBy('fecha', 'asc')
            ->orderBy('hora_inicio', 'asc');
    }
}
