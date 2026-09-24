<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Diseno extends Model
{
    protected $table = 'disenos';

    protected $fillable = [
        'titulo',
        'formato',
        'ancho',
        'alto',
        'canvas_json',
        'preview_path',
        'user_id',
    ];

    protected $casts = [
        'canvas_json' => 'array',
        'ancho' => 'integer',
        'alto' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function previewUrl(): ?string
    {
        return $this->preview_path ? asset('storage/'.$this->preview_path) : null;
    }

    public function paginas(): HasMany
    {
        return $this->hasMany(DisenoPagina::class)->orderBy('orden')->orderBy('id');
    }

    /** Formato que espera el editor (OpenDesign). */
    public function paraEditor(): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->titulo,
            'canvas_json' => $this->canvas_json ? json_encode($this->canvas_json) : '{}',
            'width' => (int) $this->ancho,
            'height' => (int) $this->alto,
            'thumbnail_url' => $this->preview_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->preview_path).'?v='.$this->updated_at?->timestamp : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
