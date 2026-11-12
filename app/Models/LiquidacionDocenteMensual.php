<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Liquidación mensual de un docente (borrador → aprobada → pagada).
 * `detalle` guarda la foto del cálculo: clases dictadas, ajustes y montos por sede.
 */
class LiquidacionDocenteMensual extends Model
{
    public const BORRADOR = 'borrador';

    public const APROBADA = 'aprobada';

    public const PAGADA = 'pagada';

    public const ESTADOS = [
        self::BORRADOR => 'En preparación',
        self::APROBADA => 'Aprobada',
        self::PAGADA => 'Pagada',
    ];

    protected $table = 'liquidaciones_docentes';

    protected $fillable = [
        'profesor_id', 'anio', 'mes', 'modalidad', 'horas_dictadas', 'horas_ajuste', 'monto_horas',
        'monto_porcentaje', 'monto_total', 'estado', 'detalle', 'observaciones', 'generada_por',
    ];

    protected $casts = [
        'anio' => 'integer',
        'mes' => 'integer',
        'horas_dictadas' => 'float',
        'horas_ajuste' => 'float',
        'monto_horas' => 'float',
        'monto_porcentaje' => 'float',
        'monto_total' => 'float',
        'detalle' => 'array',
        'gasto_ids' => 'array',
        'aprobada_at' => 'datetime',
        'pagada_at' => 'datetime',
    ];

    /** @return BelongsTo<Profesor, $this> */
    public function profesor(): BelongsTo
    {
        return $this->belongsTo(Profesor::class);
    }

    /** @return BelongsTo<User, $this> */
    public function aprobadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobada_por');
    }

    /** @return BelongsTo<User, $this> */
    public function pagadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pagada_por');
    }

    public function etiquetaEstado(): string
    {
        return self::ESTADOS[$this->estado] ?? (string) $this->estado;
    }

    public function periodo(): string
    {
        return ucfirst(\Carbon\Carbon::create($this->anio, $this->mes, 1)->locale('es')->translatedFormat('F Y'));
    }

    public function numeroRecibo(): string
    {
        return sprintf('LD-%04d%02d-%05d', $this->anio, $this->mes, $this->id);
    }

    public function horasTotales(): float
    {
        return round($this->horas_dictadas + $this->horas_ajuste, 2);
    }
}
