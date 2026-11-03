<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagoDetalle extends Model
{
    use Auditable;

    protected $table = 'pago_detalles';

    protected $fillable = [
        'pago_id',
        'alumno_id',
        'cuota_id',
        'monto',
        'abono_profesor',
        'abono_base',
        'abono_porcentaje',
        'abono_nota',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'abono_profesor' => 'decimal:2',
        'abono_base' => 'decimal:2',
        'abono_porcentaje' => 'decimal:2',
    ];

    /**
     * Los detalles de pagos anulados no cuentan (saldos, reportes, "ya pagó").
     * Desde el propio pago se ven igual (Pago::detalles quita este scope).
     */
    protected static function booted(): void
    {
        static::addGlobalScope('pago_vigente', function (\Illuminate\Database\Eloquent\Builder $query) {
            static $hayAnulacion = null;
            $hayAnulacion ??= \Illuminate\Support\Facades\Schema::hasColumn('pagos', 'anulado_at');
            if ($hayAnulacion) {
                $query->whereHas('pago', fn ($q) => $q->whereNull('pagos.anulado_at'));
            }
        });
    }

    /** @return BelongsTo<Pago, $this> */
    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }

    /** @return BelongsTo<Alumno, $this> */
    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    /** @return BelongsTo<Cuota, $this> */
    public function cuota(): BelongsTo
    {
        return $this->belongsTo(Cuota::class);
    }
}
