<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Ensayo para un show, con su propia lista de asistencia. */
class ShowEnsayo extends Model
{
    protected $table = 'show_ensayos';

    protected $fillable = ['show_id', 'fecha', 'hora_inicio', 'hora_fin', 'lugar', 'notas'];

    protected $casts = [
        'fecha' => 'date',
        'hora_inicio' => 'datetime',
        'hora_fin' => 'datetime',
    ];

    /** @return BelongsTo<Show, $this> */
    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    /** @return HasMany<ShowEnsayoAsistencia, $this> */
    public function asistencias(): HasMany
    {
        return $this->hasMany(ShowEnsayoAsistencia::class);
    }
}
