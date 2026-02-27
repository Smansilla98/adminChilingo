<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrdenCompra extends Model
{
    protected $table = 'ordenes_compra';

    public const ESTADOS = [
        'borrador' => 'Borrador',
        'enviada' => 'Enviada',
        'aprobada' => 'Aprobada',
        'recibida' => 'Recibida',
        'cancelada' => 'Cancelada',
    ];

    public const MOTIVOS = [
        'reposicion' => 'Reposición',
        'nuevos_talleres' => 'Nuevos talleres',
        'nuevos_alumnos' => 'Nuevos alumnos',
        'mixto' => 'Mixto',
        'otro' => 'Otro',
    ];

    protected $fillable = [
        'sede_id',
        'created_by',
        'motivo',
        'estado',
        'fecha_objetivo',
        'justificacion',
        'total_estimado',
    ];

    protected $casts = [
        'fecha_objetivo' => 'date',
        'total_estimado' => 'decimal:2',
    ];

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrdenCompraItem::class);
    }
}

