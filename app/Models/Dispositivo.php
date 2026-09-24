<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dispositivo extends Model
{
    protected $fillable = ['user_id', 'token', 'plataforma', 'nombre', 'ultimo_uso_at'];

    protected $casts = ['ultimo_uso_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
