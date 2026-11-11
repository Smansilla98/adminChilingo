<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Código de recuperación de contraseña (solo se guarda el hash). */
class RecuperacionClave extends Model
{
    protected $table = 'recuperaciones_clave';

    protected $fillable = ['user_id', 'canal', 'codigo_hash', 'vence_at', 'intentos', 'usado_at'];

    protected $casts = ['vence_at' => 'datetime', 'usado_at' => 'datetime', 'intentos' => 'integer'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
