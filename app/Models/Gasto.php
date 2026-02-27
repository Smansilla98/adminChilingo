<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Gasto extends Model
{
    protected $fillable = [
        'sede_id',
        'bloque_id',
        'created_by',
        'fecha',
        'tipo',
        'subtipo',
        'descripcion',
        'monto',
        'proveedor',
        'notas',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto' => 'decimal:2',
    ];

    public const TIPOS = [
        'sueldo' => 'Sueldos',
        'alquiler' => 'Alquiler',
        'servicio' => 'Servicios (luz, agua, etc.)',
        'reparacion' => 'Reparaciones',
        'insumo' => 'Insumos',
        'servicio_externo' => 'Servicios externos',
        'otro' => 'Otros',
    ];

    /** Subtipos sugeridos por tipo (para reportes) */
    public const SUBTIPOS = [
        'servicio' => ['luz' => 'Luz', 'agua' => 'Agua', 'gas' => 'Gas', 'internet' => 'Internet', 'otro' => 'Otro'],
        'reparacion' => ['edilicio' => 'Edilicio', 'tambores' => 'Tambores', 'otro' => 'Otro'],
        'servicio_externo' => ['electricista' => 'Electricista', 'plomero' => 'Plomero', 'cortador_pasto' => 'Cortador de pasto', 'pintor' => 'Pintor', 'otro' => 'Otro'],
    ];

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function bloque(): BelongsTo
    {
        return $this->belongsTo(Bloque::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

