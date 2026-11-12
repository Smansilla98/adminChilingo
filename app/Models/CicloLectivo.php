<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $anio
 * @property string $nombre
 * @property Carbon $inicio
 * @property Carbon $fin
 * @property Carbon|null $cerrado_at
 */
class CicloLectivo extends Model
{
    protected $table = 'ciclos_lectivos';

    protected $fillable = ['anio', 'nombre', 'inicio', 'fin', 'cerrado_at', 'abierto_por'];

    protected $casts = ['inicio' => 'date', 'fin' => 'date', 'cerrado_at' => 'datetime'];

    /** Ciclo en curso: el de año más alto que no está cerrado. */
    public static function vigente(): ?self
    {
        return self::query()->whereNull('cerrado_at')->orderByDesc('anio')->first();
    }

    /** @return HasMany<Reinscripcion, $this> */
    public function reinscripciones(): HasMany
    {
        return $this->hasMany(Reinscripcion::class);
    }
}
