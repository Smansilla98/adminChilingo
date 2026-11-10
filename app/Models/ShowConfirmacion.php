<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Respuesta de un alumno convocado a un show (voy / no voy / tal vez) y si fue. */
class ShowConfirmacion extends Model
{
    protected $table = 'show_confirmaciones';

    public const RESPUESTAS = [
        'voy' => 'Voy',
        'tal_vez' => 'Tal vez',
        'no_voy' => 'No voy',
    ];

    protected $fillable = ['show_id', 'alumno_id', 'respuesta', 'comentario', 'respondido_at', 'asistio'];

    protected $casts = [
        'respondido_at' => 'datetime',
        'asistio' => 'boolean',
    ];

    /** @return BelongsTo<Show, $this> */
    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    /** @return BelongsTo<Alumno, $this> */
    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }
}
