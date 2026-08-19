<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioMovimiento extends Model
{
    public const TIPOS = [
        'ingreso' => 'Ingreso',
        'sede' => 'Cambio de sede',
        'asignado' => 'Asignado a grupo / uso',
        'reparacion' => 'Reparación',
        'evento' => 'Salió a evento / show',
        'retorno' => 'Retorno',
    ];

    protected $fillable = [
        'inventario_item_id',
        'user_id',
        'sede_id',
        'tipo',
        'nota',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventarioItem::class, 'inventario_item_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function etiquetaTipo(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }
}
