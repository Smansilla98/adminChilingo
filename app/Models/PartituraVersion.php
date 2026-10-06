<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Versión publicada de la partitura de un toque (se puede restaurar desde el editor). */
class PartituraVersion extends Model
{
    protected $table = 'partitura_versiones';

    protected $fillable = ['programa_ritmo_id', 'numero', 'score', 'autor', 'nota'];

    protected $casts = ['score' => 'array', 'numero' => 'integer'];

    public function toque(): BelongsTo
    {
        return $this->belongsTo(ProgramaRitmo::class, 'programa_ritmo_id');
    }
}
