<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * En qué anda un alumno con un toque del programa. No es una nota: dice si todavía
 * no lo empezó, si lo está trabajando o si ya lo toca con el bloque.
 */
class ProgresoToque extends Model
{
    public const NO_EMPEZADO = 'no_empezado';

    public const EN_PROCESO = 'en_proceso';

    /** Clave interna "aprobado": en pantalla se dice «Lo toca con el bloque». */
    public const APROBADO = 'aprobado';

    public const ESTADOS = [
        self::NO_EMPEZADO => 'Todavía no',
        self::EN_PROCESO => 'Lo está trabajando',
        self::APROBADO => 'Lo toca con el bloque',
    ];

    protected $table = 'progreso_toques';

    protected $fillable = ['alumno_id', 'programa_ritmo_id', 'estado', 'fecha', 'user_id', 'bloque_id', 'nota'];

    protected $casts = ['fecha' => 'date'];

    /** @return BelongsTo<Alumno, $this> */
    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    /** @return BelongsTo<ProgramaRitmo, $this> */
    public function toque(): BelongsTo
    {
        return $this->belongsTo(ProgramaRitmo::class, 'programa_ritmo_id');
    }

    /** @return BelongsTo<User, $this> */
    public function docente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Bloque, $this> */
    public function bloque(): BelongsTo
    {
        return $this->belongsTo(Bloque::class);
    }

    public static function etiqueta(?string $estado): string
    {
        return self::ESTADOS[$estado ?? self::NO_EMPEZADO] ?? self::ESTADOS[self::NO_EMPEZADO];
    }
}
