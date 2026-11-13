<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Ciclo lectivo de un año (N). Sus cuotas van de marzo N a febrero N+1; el académico, de marzo a
 * diciembre N. Enero y febrero son taller de verano, con la gira a Villa Gesell en paralelo (ver CalendarioAcademico).
 *
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

    /**
     * Fechas por defecto de cada módulo para un año N.
     *
     * @return array<string, array{inicio: string, fin: string}>
     */
    public static function fechasModulos(int $anio): array
    {
        return [
            CicloModulo::CUOTAS => ['inicio' => $anio.'-03-01', 'fin' => ($anio + 1).'-02-28'],
            CicloModulo::ACADEMICO => ['inicio' => $anio.'-03-01', 'fin' => $anio.'-12-15'],
            CicloModulo::REINSCRIPCION => ['inicio' => $anio.'-02-01', 'fin' => $anio.'-03-15'],
        ];
    }

    /**
     * Meses del período de cuotas: marzo N a febrero N+1.
     *
     * @return list<array{anio: int, mes: int}>
     */
    public static function periodoCuotas(int $anio): array
    {
        $meses = [];
        for ($i = 0; $i < 12; $i++) {
            $m = Carbon::create($anio, 3, 1)->addMonths($i);
            $meses[] = ['anio' => (int) $m->year, 'mes' => (int) $m->month];
        }

        return $meses;
    }

    /** @return HasMany<CicloModulo, $this> */
    public function modulos(): HasMany
    {
        return $this->hasMany(CicloModulo::class, 'ciclo_lectivo_id');
    }

    /** @return HasMany<Reinscripcion, $this> */
    public function reinscripciones(): HasMany
    {
        return $this->hasMany(Reinscripcion::class);
    }
}
