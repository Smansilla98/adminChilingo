<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShowEnsayoAsistencia extends Model
{
    protected $table = 'show_ensayo_asistencias';

    protected $fillable = ['show_ensayo_id', 'alumno_id', 'presente'];

    protected $casts = ['presente' => 'boolean'];

    /** @return BelongsTo<Alumno, $this> */
    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }
}
