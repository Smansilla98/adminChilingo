<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'punto_encuentro',
        'hora_encuentro',
        'transporte',
        'logistica_notas',
    ];

    protected $casts = [
        'fecha' => 'date',
        'hora_inicio' => 'datetime',
        'hora_fin' => 'datetime',
        'convocatoria_abierta' => 'boolean',
        'hora_encuentro' => 'datetime',
    ];

    /** @return BelongsToMany<Bloque, $this> */
    public function bloques(): BelongsToMany
    {
        return $this->belongsToMany(Bloque::class, 'show_bloque')->withTimestamps();
    }

    /** @return HasMany<ShowConfirmacion, $this> */
    public function confirmaciones(): HasMany
    {
        return $this->hasMany(ShowConfirmacion::class);
    }

    /** @return HasMany<ShowTema, $this> */
    public function temas(): HasMany
    {
        return $this->hasMany(ShowTema::class)->orderBy('orden')->orderBy('id');
    }

    /** @return HasMany<ShowEnsayo, $this> */
    public function ensayos(): HasMany
    {
        return $this->hasMany(ShowEnsayo::class)->orderBy('fecha')->orderBy('hora_inicio');
    }

    /** @return HasMany<ShowInstrumento, $this> */
    public function instrumentos(): HasMany
    {
        return $this->hasMany(ShowInstrumento::class)->orderBy('id');
    }

    public function scopeProximos($query)
    {
        return $query->where('fecha', '>=', now()->toDateString())
            ->orderBy('fecha')->orderBy('hora_inicio');
    }
}
