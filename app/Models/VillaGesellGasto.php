<?php

namespace App\Models;

use App\Support\VillaGesellCalculo;
use Illuminate\Database\Eloquent\Model;

class VillaGesellGasto extends Model
{
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
    ];

    public function proyectado(int $diasGira): float
    {
        return VillaGesellCalculo::proyectarGasto((float) $this->monto, (string) $this->tipo, (string) $this->modo, $diasGira);
    }
}
