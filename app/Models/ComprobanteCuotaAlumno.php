<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComprobanteCuotaAlumno extends Model
{
    protected $table = 'comprobantes_cuota_alumnos';

    protected $fillable = [
        'alumno_id',
        'sede_id',
        'fecha_pago',
        'monto_total',
        'comprobante_path',
        'notas',
        'estado',
        'pago_id',
    ];

    protected $casts = [
        'fecha_pago' => 'date',
        'monto_total' => 'decimal:2',
    ];

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ComprobanteCuotaAlumnoItem::class, 'comprobante_cuota_alumno_id');
    }

    public function estaPendiente(): bool
    {
        return $this->estado === 'pendiente';
    }

    public function estaPagado(): bool
    {
        return $this->estado === 'pagado' || (bool) $this->pago_id;
    }

    public function etiquetaEstado(): string
    {
        return match ($this->estado) {
            'pendiente' => 'Pendiente de revisión',
            'pagado' => 'Pagado',
            'visto' => 'Visto',
            default => (string) $this->estado,
        };
    }
}
