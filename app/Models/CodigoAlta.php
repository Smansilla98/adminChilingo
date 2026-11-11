<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $persona_id
 * @property string $codigo_hash
 * @property Carbon $vence_at
 * @property Carbon|null $usado_at
 * @property int|null $creado_por
 */
class CodigoAlta extends Model
{
    protected $table = 'codigos_alta';

    protected $fillable = ['persona_id', 'codigo_hash', 'vence_at', 'usado_at', 'creado_por'];

    protected $casts = ['vence_at' => 'datetime', 'usado_at' => 'datetime'];

    /** @return BelongsTo<Persona, $this> */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }
}
