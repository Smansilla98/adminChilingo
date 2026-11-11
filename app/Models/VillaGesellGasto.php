<?php

namespace App\Models;

use App\Support\VillaGesellCalculo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property int|null $eliminado_por
 * @property string|null $motivo_eliminacion
 */
class VillaGesellGasto extends Model
{
    use SoftDeletes;

    protected $table = 'villa_gesell_gastos';

    public const TIPOS = [
        'fijo' => 'Gasto fijo',
        'traslado' => 'Traslados',
        'nafta' => 'Nafta',
        'diario' => 'Gasto diario',
        'otro' => 'Otro',
    ];

    public const MODOS = [
        'total' => 'Valor único (no se multiplica)',
        'por_dia' => 'Valor por día × cantidad de días',
    ];

    protected $fillable = [
        'tipo',
        'concepto',
        'monto',
        'modo',
        'fecha',
        'notas',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'fecha' => 'date',
        'deleted_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function eliminadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'eliminado_por');
    }

    public function proyectado(int $diasGira): float
    {
        return VillaGesellCalculo::proyectarGasto((float) $this->monto, (string) $this->tipo, (string) $this->modo, $diasGira);
    }
}
