<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un tema de la lista del show: un toque del programa o un título libre. */
class ShowTema extends Model
{
    protected $table = 'show_temas';

    protected $fillable = ['show_id', 'orden', 'programa_ritmo_id', 'titulo', 'notas'];

    protected $casts = ['orden' => 'integer'];

    /** @return BelongsTo<ProgramaRitmo, $this> */
    public function toque(): BelongsTo
    {
        return $this->belongsTo(ProgramaRitmo::class, 'programa_ritmo_id');
    }

    public function nombre(): string
    {
        return (string) ($this->titulo ?: ($this->toque->nombre ?? 'Tema'));
    }
}
