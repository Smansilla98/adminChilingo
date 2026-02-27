<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdenCompraItem extends Model
{
    protected $table = 'orden_compra_items';

    protected $fillable = [
        'orden_compra_id',
        'tipo',
        'familia',
        'descripcion',
        'marca',
        'modelo',
        'medida',
        'cantidad',
        'unidad',
        'precio_estimado',
        'subtotal_estimado',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'precio_estimado' => 'decimal:2',
        'subtotal_estimado' => 'decimal:2',
    ];

    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenCompra::class, 'orden_compra_id');
    }
}

