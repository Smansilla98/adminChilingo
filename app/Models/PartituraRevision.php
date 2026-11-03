<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartituraRevision extends Model
{
    protected $table = 'partitura_revisiones';

    protected $fillable = ['programa_ritmo_id', 'seccion', 'compas', 'nota', 'nota_hash', 'validado_por', 'validado_at'];

    protected $casts = [
        'compas' => 'integer',
        'validado_at' => 'datetime',
    ];

    /** @return BelongsTo<ProgramaRitmo, $this> */
    public function programaRitmo(): BelongsTo
    {
        return $this->belongsTo(ProgramaRitmo::class);
    }

    /** @return BelongsTo<User, $this> */
    public function validador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validado_por');
    }
}
