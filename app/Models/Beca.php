<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Beca extends Model
{
    use Auditable;

    public const TIPOS = [
        'porcentaje' => 'Porcentaje',
        'monto_fijo' => 'Monto fijo por cuota',
        'total' => 'Beca total',
    ];

    public const ESTADOS = [
        'activa' => 'Activa',
        'suspendida' => 'Suspendida',
        'finalizada' => 'Finalizada',
    ];

    protected $fillable = [
        'alumno_id', 'tipo', 'porcentaje', 'monto', 'bloque_id', 'sede_id', 'motivo',
        'fecha_inicio', 'fecha_fin', 'estado', 'observaciones', 'otorgada_por',
    ];

    protected $casts = [
        'porcentaje' => 'decimal:2',
        'monto' => 'decimal:2',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
    ];

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    public function bloque(): BelongsTo
    {
        return $this->belongsTo(Bloque::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function otorgadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'otorgada_por');
    }

    /** Becas activas vigentes en una fecha. */
    public function scopeVigentesEn(Builder $query, CarbonInterface $fecha): Builder
    {
        $dia = $fecha->toDateString();

        return $query->where('estado', 'activa')
            ->where('fecha_inicio', '<=', $dia)
            ->where(fn (Builder $q) => $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $dia));
    }

    /** ¿Aplica a una cuota de este bloque/sede? */
    public function aplicaA(?int $bloqueId, ?int $sedeId): bool
    {
        if ($this->bloque_id && (int) $this->bloque_id !== (int) $bloqueId) {
            return false;
        }
        if ($this->sede_id && (int) $this->sede_id !== (int) $sedeId) {
            return false;
        }

        return true;
    }

    /** Descuento que produce sobre un monto. Nunca supera el monto. */
    public function descuentoSobre(float $monto): float
    {
        $descuento = match ($this->tipo) {
            'total' => $monto,
            'porcentaje' => $monto * ((float) $this->porcentaje / 100),
            'monto_fijo' => (float) $this->monto,
            default => 0.0,
        };

        return round(min(max($descuento, 0), $monto), 2);
    }

    public function etiqueta(): string
    {
        return match ($this->tipo) {
            'total' => 'Beca total',
            'porcentaje' => 'Beca '.rtrim(rtrim(number_format((float) $this->porcentaje, 2, ',', '.'), '0'), ',').'%',
            'monto_fijo' => 'Beca $'.number_format((float) $this->monto, 0, ',', '.'),
            default => 'Beca',
        };
    }
}
