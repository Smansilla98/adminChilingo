<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asistencia extends Model
{
    /** Leyenda tipo Excel: P / T / J / I / F / S */
    public static function letraTipo(?string $tipo): string
    {
        return match ($tipo) {
            'presente' => 'P',
            'tarde' => 'T',
            'ausencia_justificada', 'justificado' => 'J',
            'ausencia_injustificada', 'ausente' => 'I',
            'feriado' => 'F',
            'sin_clases' => 'S',
            default => '',
        };
    }

    public const TIPOS_ASISTENCIA = [
        'presente' => 'Presente',
        'tarde' => 'Tarde',
        'ausencia_justificada' => 'Ausencia justificada',
        'ausencia_injustificada' => 'Ausencia injustificada',
        'feriado' => 'Feriado',
        'sin_clases' => 'Sin clases',
        // Legacy (datos ya guardados)
        'ausente' => 'Ausencia injustificada',
        'justificado' => 'Ausencia justificada',
    ];

    /** Tipos que se pueden elegir en formularios / matriz (sin aliases legacy). */
    public static function tiposEditables(): array
    {
        return array_diff_key(self::TIPOS_ASISTENCIA, array_flip(['ausente', 'justificado']));
    }

    public static function reglaValidacionTipo(): string
    {
        return 'nullable|string|in:'.implode(',', array_keys(self::tiposEditables()));
    }

    public static function esPresente(?string $tipo): bool
    {
        return in_array($tipo, ['presente', 'tarde'], true);
    }

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
                $asistencia->presente = self::esPresente($asistencia->tipo_asistencia);
            }
        });
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    public function bloque(): BelongsTo
    {
        return $this->belongsTo(Bloque::class);
    }
}
