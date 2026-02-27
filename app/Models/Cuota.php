<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cuota extends Model
{
    protected $table = 'cuotas';

    protected $fillable = [
        'nombre',
        'año',
        'mes',
        'monto',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'año' => 'integer',
        'mes' => 'integer',
        'monto' => 'decimal:2',
        'activo' => 'boolean',
    ];

    public function pagoDetalles(): HasMany
    {
        return $this->hasMany(PagoDetalle::class);
    }

    public function getNombreMesAttribute(): ?string
    {
        if (!$this->mes) {
            return null;
        }
        $meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        return $meses[$this->mes] ?? (string) $this->mes;
    }
}
