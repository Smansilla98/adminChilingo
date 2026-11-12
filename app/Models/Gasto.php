<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property int|null $eliminado_por
 * @property string|null $motivo_eliminacion
 * @property-read float|int|null $total suma calculada en reportes
 */
class Gasto extends Model
{
    use Auditable, SoftDeletes;

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
        'aprobado_at' => 'datetime',
        'deleted_at' => 'datetime',
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

    /** @return BelongsTo<Sede, $this> */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /** @return BelongsTo<Bloque, $this> */
    public function bloque(): BelongsTo
    {
        return $this->belongsTo(Bloque::class);
    }

    /** @return BelongsTo<User, $this> */
    public function eliminadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'eliminado_por');
    }

    /** @return BelongsTo<User, $this> */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
