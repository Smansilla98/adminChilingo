<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Alumnos de una misma familia (para el descuento por grupo familiar).
 *
 * @property int $id
 * @property string $nombre
 * @property string|null $notas
 */
class GrupoFamiliar extends Model
{
    protected $table = 'grupos_familiares';

    protected $fillable = ['nombre', 'notas', 'creado_por'];

    /** @return BelongsToMany<Alumno, $this> */
    public function alumnos(): BelongsToMany
    {
        return $this->belongsToMany(Alumno::class, 'grupo_familiar_alumno')->withTimestamps();
    }

    /**
     * Alumno que paga la cuota completa en su grupo: el de menor número entre los activos.
     * El resto recibe el descuento familiar. Null si no tiene grupo con otro activo.
     *
     * @return array{grupo: string, paga_completo: bool}|null
     */
    public static function situacionDe(int $alumnoId): ?array
    {
        $grupoId = \Illuminate\Support\Facades\DB::table('grupo_familiar_alumno')->where('alumno_id', $alumnoId)->value('grupo_familiar_id');
        if (! $grupoId) {
            return null;
        }
        $activos = \Illuminate\Support\Facades\DB::table('grupo_familiar_alumno')
            ->join('alumnos', 'alumnos.id', '=', 'grupo_familiar_alumno.alumno_id')
            ->where('grupo_familiar_id', $grupoId)->where('alumnos.activo', true)
            ->orderBy('alumnos.id')->pluck('alumnos.id')->map(fn ($id) => (int) $id);
        if ($activos->count() < 2 || ! $activos->contains($alumnoId)) {
            return null;
        }

        return [
            'grupo' => (string) self::query()->whereKey($grupoId)->value('nombre'),
            'paga_completo' => $activos->first() === $alumnoId,
        ];
    }
}
