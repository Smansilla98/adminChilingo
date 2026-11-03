<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VillaGesellTocada extends Model
{
    protected $table = 'villa_gesell_tocadas';

    protected $fillable = [
        'dia_id',
        'orden',
        'hora',
        'que',
        'donde',
        'notas',
    ];

    /** @return BelongsTo<VillaGesellDia, $this> */
    public function dia(): BelongsTo
    {
        return $this->belongsTo(VillaGesellDia::class, 'dia_id');
    }
}
