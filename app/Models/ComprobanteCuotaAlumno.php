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

    public function items(): HasMany
    {
        return $this->hasMany(ComprobanteCuotaAlumnoItem::class, 'comprobante_cuota_alumno_id');
    }

    public function estaPendiente(): bool
    {
        return $this->estado === 'pendiente';
    }
}
