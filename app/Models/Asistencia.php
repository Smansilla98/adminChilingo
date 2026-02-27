<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asistencia extends Model
{
    /** Tipos de asistencia (igual que hoja "Tipos de asistencia" del Excel Chilinga 2025) */
    public const TIPOS_ASISTENCIA = [
        'presente'              => 'Presente',
        'tarde'                 => 'Tarde',
        'ausencia_justificada'  => 'Ausencia justificada',
        'ausencia_injustificada'=> 'Ausencia injustificada',
        // Legacy (datos ya guardados)
        'ausente'               => 'Ausencia injustificada',
        'justificado'           => 'Ausencia justificada',
    ];

    protected $fillable = [
        'alumno_id',
        'bloque_id',
        'fecha',
        'presente',
        'tipo_asistencia',
    ];

    protected $casts = [
        'fecha' => 'date',
        'presente' => 'boolean',
    ];

    protected static function booted()
    {
        static::saving(function (Asistencia $asistencia) {
            if ($asistencia->isDirty('tipo_asistencia')) {
                $asistencia->presente = ($asistencia->tipo_asistencia === 'presente' || $asistencia->tipo_asistencia === 'tarde');
            }
        });
    }

    /**
     * Relación con alumno
     */
    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    /**
     * Relación con bloque
     */
    public function bloque(): BelongsTo
    {
        return $this->belongsTo(Bloque::class);
    }
}
