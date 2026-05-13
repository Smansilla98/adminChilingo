<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sede extends Model
{
    protected $fillable = [
        'nombre',
        'direccion',
        'tipo_propiedad',
        'costo_alquiler_mensual',
        'coordinador_id',
        'activo',
        'liquidacion_retencion_escuela',
        'liquidacion_porc_docente',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'liquidacion_retencion_escuela' => 'decimal:2',
        'liquidacion_porc_docente' => 'decimal:2',
    ];

    /**
     * Base sobre la que se aplica el % al docente (monto cuota de referencia − retención fija escuela).
     */
    public function baseLiquidacionDocenteDesdeCuota(float $montoCuotaReferencia): float
    {
        $ret = (float) ($this->liquidacion_retencion_escuela ?? 0);

        return max(0, round($montoCuotaReferencia - $ret, 2));
    }

    public function porcentajeLiquidacionDocente(): float
    {
        $p = (float) ($this->liquidacion_porc_docente ?? 40);

        return max(0.0, min(100.0, $p));
    }

    /**
     * Profesor coordinador de esta sede (ej: Banfield)
     */
    public function coordinador(): BelongsTo
    {
        return $this->belongsTo(Profesor::class, 'coordinador_id');
    }

    /**
     * Relación con bloques
     */
    public function bloques(): HasMany
    {
        return $this->hasMany(Bloque::class);
    }

    /**
     * Alumnos que tienen esta sede como principal (sede_id)
     */
    public function alumnos(): HasMany
    {
        return $this->hasMany(Alumno::class);
    }

    /**
     * Relación con eventos
     */
    public function eventos(): HasMany
    {
        return $this->hasMany(Evento::class);
    }

    /**
     * Inventario asignado a esta sede
     */
    public function inventarioItems(): HasMany
    {
        return $this->hasMany(InventarioItem::class);
    }

    public function gastos(): HasMany
    {
        return $this->hasMany(Gasto::class);
    }

    /**
     * Obtener alumnos activos
     */
    public function alumnosActivos(): HasMany
    {
        return $this->alumnos()->where('activo', true);
    }
}
