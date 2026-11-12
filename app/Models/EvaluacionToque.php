<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Repaso de toques con devolución del docente (en el código, "evaluación").
 * El resultado describe cómo sale el toque con el bloque, nunca un puntaje.
 */
class EvaluacionToque extends Model
{
    public const RESULTADOS = [
        'lo_toca' => 'Lo toca con el bloque',
        'en_camino' => 'Va en camino',
        'retomar' => 'Conviene retomarlo',
    ];

    protected $table = 'evaluaciones_toque';

    protected $fillable = ['alumno_id', 'bloque_id', 'user_id', 'fecha', 'toques', 'resultado', 'devolucion', 'visible_alumno'];

    protected $casts = [
        'fecha' => 'date',
        'toques' => 'array',
        'visible_alumno' => 'boolean',
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
    public function docente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function etiquetaResultado(): string
    {
        return self::RESULTADOS[$this->resultado] ?? (string) $this->resultado;
    }

    /** @return list<int> */
    public function toqueIds(): array
    {
        return array_values(array_map('intval', $this->toques));
    }
}
