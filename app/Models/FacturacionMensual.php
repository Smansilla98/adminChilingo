<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacturacionMensual extends Model
{
    protected $table = 'facturacion_mensual';

    protected $fillable = [
        'sede_id',
        'año',
        'mes',
        'cantidad_alumnos',
        'monto_facturado',
        'monto_previsto',
        'notas',
    ];

    protected $casts = [
        'año' => 'integer',
        'mes' => 'integer',
        'cantidad_alumnos' => 'integer',
        'monto_facturado' => 'decimal:2',
        'monto_previsto' => 'decimal:2',
    ];

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public static function nombresMeses(): array
    {
        return [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];
    }

    public function getNombreMesAttribute(): string
    {
        return self::nombresMeses()[$this->mes] ?? (string) $this->mes;
    }
}
