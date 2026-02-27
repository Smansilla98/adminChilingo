<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BloqueHorario extends Model
{
    protected $table = 'bloque_horarios';

    protected $fillable = ['bloque_id', 'dia_semana', 'hora_inicio', 'hora_fin'];

    protected $casts = [
        'dia_semana' => 'integer',
        'hora_inicio' => 'datetime',
        'hora_fin' => 'datetime',
    ];

    public const DIAS_SEMANA = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    public function bloque(): BelongsTo
    {
        return $this->belongsTo(Bloque::class);
    }

    public function getNombreDiaAttribute(): string
    {
        return self::DIAS_SEMANA[$this->dia_semana] ?? (string) $this->dia_semana;
    }
}
