<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Reinscripción de un alumno a un ciclo lectivo. */
class Reinscripcion extends Model
{
    public const PENDIENTE = 'pendiente';

    public const CONFIRMADA = 'confirmada';

    public const BAJA = 'baja';

    protected $table = 'reinscripciones';

    protected $fillable = ['ciclo_lectivo_id', 'alumno_id', 'estado', 'confirmada_at', 'resuelta_por', 'resuelta_at'];

    protected $casts = ['confirmada_at' => 'datetime', 'resuelta_at' => 'datetime'];

    /** @return BelongsTo<CicloLectivo, $this> */
    public function ciclo(): BelongsTo
    {
        return $this->belongsTo(CicloLectivo::class, 'ciclo_lectivo_id');
    }

    /** @return BelongsTo<Alumno, $this> */
    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }
}
