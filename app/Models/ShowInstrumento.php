<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Instrumento que va al show: un ítem del inventario o una descripción libre. */
class ShowInstrumento extends Model
{
    protected $table = 'show_instrumentos';

    protected $fillable = ['show_id', 'inventario_item_id', 'descripcion', 'cantidad'];

    protected $casts = ['cantidad' => 'integer'];

    /** @return BelongsTo<InventarioItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventarioItem::class, 'inventario_item_id');
    }

    public function nombre(): string
    {
        if ($this->descripcion) {
            return (string) $this->descripcion;
        }
        $item = $this->item;

        return $item ? $item->nombre.($item->codigo ? ' ('.$item->codigo.')' : '') : 'Instrumento';
    }
}
