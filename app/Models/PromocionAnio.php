<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Propuesta de pasar al año siguiente. La arma el sistema cuando el alumno ya toca
 * con el bloque los toques obligatorios de su año; la confirma (o descarta) el
 * docente o la coordinación.
 */
class PromocionAnio extends Model
{
    public const PROPUESTA = 'propuesta';

    public const CONFIRMADA = 'confirmada';

    public const DESCARTADA = 'descartada';

    public const ESTADOS = [
        self::PROPUESTA => 'Propuesta',
        self::CONFIRMADA => 'Confirmada',
        self::DESCARTADA => 'Descartada',
    ];

    protected $table = 'promociones_anio';

    protected $fillable = ['alumno_id', 'anio_desde', 'anio_hasta', 'estado', 'resuelto_por', 'resuelto_at', 'nota'];

    protected $casts = [
        'anio_desde' => 'integer',
        'anio_hasta' => 'integer',
        'resuelto_at' => 'datetime',
    ];

    /** @return BelongsTo<Alumno, $this> */
    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    /** @return BelongsTo<User, $this> */
    public function resueltoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelto_por');
    }
}
