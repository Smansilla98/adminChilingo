<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pago extends Model
{
    protected $fillable = [
        'fecha_pago',
        'monto_total',
        'comprobante_path',
        'notas',
        'registrado_por',
    ];

    protected $casts = [
        'fecha_pago' => 'date',
        'monto_total' => 'decimal:2',
    ];

    public function detalles(): HasMany
    {
        return $this->hasMany(PagoDetalle::class);
    }

    public function alumnos()
    {
        return $this->belongsToMany(Alumno::class, 'pago_detalles')
            ->withPivot('cuota_id', 'monto')
            ->withTimestamps();
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
