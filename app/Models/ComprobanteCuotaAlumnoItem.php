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
        'anio',
        'mes',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
    ];

    /** Nombre para mostrar: la cuota, o el bloque y el mes si es un pago extra sin cuota emitida. */
    public function etiqueta(): string
    {
        $cuota = $this->cuota;
        if ($cuota instanceof Cuota) {
            return (string) $cuota->nombre;
        }

        return 'Pago extra'.($this->mes && $this->anio ? sprintf(' · %02d/%d', $this->mes, $this->anio) : '');
    }

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
