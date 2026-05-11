<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComprobanteCuotaAlumnoItem extends Model
{
    protected $table = 'comprobante_cuota_alumno_items';

    protected $fillable = [
        'comprobante_cuota_alumno_id',
        'cuota_id',
        'bloque_id',
        'monto',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
    ];

    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(ComprobanteCuotaAlumno::class, 'comprobante_cuota_alumno_id');
    }

    public function cuota(): BelongsTo
    {
        return $this->belongsTo(Cuota::class);
    }

    public function bloque(): BelongsTo
    {
        return $this->belongsTo(Bloque::class);
    }
}
