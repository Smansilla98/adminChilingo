<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Módulo de un ciclo lectivo con sus fechas.
 *
 * @property string $clave
 * @property Carbon $inicio
 * @property Carbon $fin
 */
class CicloModulo extends Model
{
    public const CUOTAS = 'cuotas';

    public const ACADEMICO = 'academico';

    public const REINSCRIPCION = 'reinscripcion';

    protected $table = 'ciclo_modulos';

    protected $fillable = ['ciclo_lectivo_id', 'clave', 'inicio', 'fin'];

    protected $casts = ['inicio' => 'date', 'fin' => 'date'];

    /** @return BelongsTo<CicloLectivo, $this> */
    public function ciclo(): BelongsTo
    {
        return $this->belongsTo(CicloLectivo::class, 'ciclo_lectivo_id');
    }
}
