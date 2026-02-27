<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagoDetalle extends Model
{
    protected $table = 'pago_detalles';

    protected $fillable = ['pago_id', 'alumno_id', 'cuota_id', 'monto'];

    protected $casts = [
        'monto' => 'decimal:2',
    ];

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    public function cuota(): BelongsTo
    {
        return $this->belongsTo(Cuota::class);
    }
}
