<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoordinadorArea extends Model
{
    /** Áreas de coordinación */
    public const AREAS = ['género', 'costa', 'tambores'];

    protected $table = 'coordinador_area';

    protected $fillable = ['profesor_id', 'area'];

    public function profesor(): BelongsTo
    {
        return $this->belongsTo(Profesor::class);
    }
}
