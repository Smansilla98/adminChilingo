<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ajuste manual de horas: suplencias y clases extra suman; ausencias restan. */
class AjusteHorasDocente extends Model
{
    public const TIPOS = [
        'suplencia' => 'Suplencia',
        'clase_extra' => 'Clase extra',
        'ausencia' => 'Ausencia',
        'otro' => 'Otro ajuste',
    ];

    /** Tipos que restan horas. */
    public const RESTAN = ['ausencia'];

    protected $table = 'docente_horas_ajustes';

    protected $fillable = ['profesor_id', 'bloque_id', 'sede_id', 'fecha', 'tipo', 'horas', 'motivo', 'user_id'];

    protected $casts = [
        'fecha' => 'date',
        'horas' => 'decimal:2',
    ];

    /** Horas con signo (las ausencias restan). */
    public function horasConSigno(): float
    {
        $h = abs((float) $this->horas);

        return in_array($this->tipo, self::RESTAN, true) ? -$h : $h;
    }

    /** @return BelongsTo<Profesor, $this> */
    public function profesor(): BelongsTo
    {
        return $this->belongsTo(Profesor::class);
    }

    /** @return BelongsTo<Bloque, $this> */
    public function bloque(): BelongsTo
    {
        return $this->belongsTo(Bloque::class);
    }

    /** @return BelongsTo<Sede, $this> */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }
}
