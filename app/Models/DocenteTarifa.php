<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Valor hora del docente. Sin sede = valor general y modalidad elegida para el docente;
 * con sede = valor hora para esa sede (pisa al general).
 */
class DocenteTarifa extends Model
{
    public const MODALIDADES = [
        'porcentaje' => 'Porcentaje de las cuotas cobradas (abono por sede)',
        'hora' => 'Por horas dictadas',
    ];

    protected $table = 'docente_tarifas';

    protected $fillable = ['profesor_id', 'sede_id', 'modalidad', 'valor_hora'];

    protected $casts = ['valor_hora' => 'decimal:2'];

    /** @return BelongsTo<Profesor, $this> */
    public function profesor(): BelongsTo
    {
        return $this->belongsTo(Profesor::class);
    }

    /** @return BelongsTo<Sede, $this> */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }
}
