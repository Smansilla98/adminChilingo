<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Show extends Model
{
    protected $fillable = [
        'titulo',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'lugar',
        'descripcion',
        'convocatoria_abierta',
    ];

    protected $casts = [
        'fecha' => 'date',
        'hora_inicio' => 'datetime',
        'hora_fin' => 'datetime',
        'convocatoria_abierta' => 'boolean',
    ];

    public function bloques(): BelongsToMany
    {
        return $this->belongsToMany(Bloque::class, 'show_bloque')->withTimestamps();
    }

    public function scopeProximos($query)
    {
        return $query->where('fecha', '>=', now()->toDateString())
            ->orderBy('fecha')->orderBy('hora_inicio');
    }
}
