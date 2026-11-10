<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un año del programa que el alumno cursó (o cursa: `hasta` vacío). */
class AlumnoAnio extends Model
{
    protected $table = 'alumno_anios';

    protected $fillable = ['alumno_id', 'anio', 'bloque_id', 'desde', 'hasta', 'confirmado_por', 'nota'];

    protected $casts = [
        'anio' => 'integer',
        'desde' => 'date',
        'hasta' => 'date',
    ];

    /** @return BelongsTo<Alumno, $this> */
    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    /** @return BelongsTo<Bloque, $this> */
    public function bloque(): BelongsTo
    {
        return $this->belongsTo(Bloque::class);
    }

    /** @return BelongsTo<User, $this> */
    public function confirmadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmado_por');
    }
}
