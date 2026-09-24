<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pago extends Model
{
    use Auditable;

    protected $fillable = [
        'fecha_pago',
        'monto_total',
        'comprobante_path',
        'notas',
        'registrado_por',
    ];

    protected $casts = [
        'anulado_at' => 'datetime',
        'fecha_pago' => 'date',
        'monto_total' => 'decimal:2',
    ];

    public function detalles(): HasMany
    {
        return $this->hasMany(PagoDetalle::class)->withoutGlobalScope('pago_vigente');
    }

    public function alumnos()
    {
        return $this->belongsToMany(Alumno::class, 'pago_detalles')
            ->withPivot('cuota_id', 'monto')
            ->withTimestamps();
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    public function estaAnulado(): bool
    {
        return $this->anulado_at !== null;
    }

    /** Pagos que cuentan para saldos y reportes (excluye anulados). */
    public function scopeVigentes(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNull($this->qualifyColumn('anulado_at'));
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
