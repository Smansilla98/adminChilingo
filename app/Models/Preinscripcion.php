<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nombre
 * @property string $apellido
 * @property string|null $dni
 * @property Carbon $fecha_nacimiento
 * @property string $telefono
 * @property string|null $email
 * @property int $sede_id
 * @property int|null $bloque_id
 * @property string $estado
 */
class Preinscripcion extends Model
{
    public const PENDIENTE = 'pendiente';

    public const APROBADA = 'aprobada';

    public const RECHAZADA = 'rechazada';

    protected $table = 'preinscripciones';

    protected $fillable = [
        'nombre', 'apellido', 'dni', 'fecha_nacimiento', 'telefono', 'email', 'sede_id', 'bloque_id',
        'tutor_nombre', 'tutor_dni', 'tutor_telefono', 'tutor_vinculo', 'notas',
        'estado', 'motivo_rechazo', 'alumno_id', 'resuelto_por', 'resuelto_at',
    ];

    protected $casts = ['fecha_nacimiento' => 'date', 'resuelto_at' => 'datetime'];

    public function esMenor(): bool
    {
        return $this->fecha_nacimiento->diffInYears(now()) < 18;
    }

    /** Teléfono al que se le avisa: el del tutor si es menor. */
    public function telefonoContacto(): ?string
    {
        return $this->esMenor() ? ($this->tutor_telefono ?: $this->telefono) : $this->telefono;
    }

    /** @return BelongsTo<Sede, $this> */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /** @return BelongsTo<Bloque, $this> */
    public function bloque(): BelongsTo
    {
        return $this->belongsTo(Bloque::class);
    }
}
