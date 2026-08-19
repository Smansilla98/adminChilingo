<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VillaGesellInsumo extends Model
{
    protected $table = 'villa_gesell_insumos';

    public const CATEGORIAS = [
        'percusion' => 'Percusión / parches',
        'sonido' => 'Sonido',
        'alojamiento' => 'Alojamiento',
        'comida' => 'Comida',
        'transporte' => 'Transporte',
        'merch' => 'Remeras / merch',
        'salud' => 'Botiquín / salud',
        'otro' => 'Otro',
    ];

    protected $fillable = [
        'nombre',
        'categoria',
        'cantidad',
        'unidad',
        'costo_unitario',
        'notas',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'costo_unitario' => 'decimal:2',
    ];

    public function costoTotal(): float
    {
        return (float) $this->cantidad * (float) $this->costo_unitario;
    }
}
