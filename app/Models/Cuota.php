<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cuota extends Model
{
    protected $table = 'cuotas';

    protected $fillable = [
        'bloque_id',
        'nombre',
        'año',
        'mes',
        'fecha_vencimiento',
        'monto',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'año' => 'integer',
        'mes' => 'integer',
        'fecha_vencimiento' => 'date',
        'monto' => 'decimal:2',
        'activo' => 'boolean',
    ];

    public function bloque(): BelongsTo
    {
        return $this->belongsTo(Bloque::class);
    }

    /**
     * Alumnos asignados a esta cuota (opcional). Si está vacío, la cuota aplica a todos los alumnos del bloque.
     */
    public function alumnos(): BelongsToMany
    {
        return $this->belongsToMany(Alumno::class, 'cuota_alumno')->withTimestamps();
    }

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

    /**
     * Si la cuota no tiene alumnos asignados en cuota_alumno, aplica a todos los del bloque;
     * si tiene lista, solo a esos (que además deben estar en el bloque).
     */
    public function aplicaAAlumno(Alumno $alumno): bool
    {
        if (! $this->bloque_id) {
            return false;
        }
        $enBloque = $alumno->bloques()->where('bloques.id', $this->bloque_id)->exists()
            || (int) $alumno->bloque_id === (int) $this->bloque_id;
        if (! $enBloque) {
            return false;
        }
        if ($this->alumnos()->count() === 0) {
            return true;
        }

        return $this->alumnos()->where('alumnos.id', $alumno->id)->exists();
    }
}
