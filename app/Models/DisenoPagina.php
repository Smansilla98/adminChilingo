<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisenoPagina extends Model
{
    protected $table = 'diseno_paginas';

    protected $fillable = ['diseno_id', 'titulo', 'canvas_json', 'orden'];

    protected $casts = ['orden' => 'integer'];

    public function diseno(): BelongsTo
    {
        return $this->belongsTo(Diseno::class);
    }

    /** Formato que espera el editor (OpenDesign). */
    public function paraEditor(): array
    {
        return [
            'id' => (string) $this->id,
            'design_id' => (string) $this->diseno_id,
            'title' => $this->titulo,
            'canvas_json' => $this->canvas_json ?: '{}',
            'sort_order' => (int) $this->orden,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
