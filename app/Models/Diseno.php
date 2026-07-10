<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
