<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $persona_id
 * @property string|null $tutor_nombre
 * @property string|null $tutor_dni
 * @property string|null $tutor_telefono
 * @property string|null $tutor_vinculo
 * @property bool|null $uso_imagen
 * @property Carbon|null $uso_imagen_fecha
 * @property string|null $apto_medico_path
 * @property string|null $apto_medico_nombre
 * @property Carbon|null $apto_medico_vence
 * @property Carbon|null $apto_aviso_enviado_el
 */
class LegajoMenor extends Model
{
    protected $table = 'legajos_menores';

    protected $fillable = [
        'persona_id', 'tutor_nombre', 'tutor_dni', 'tutor_telefono', 'tutor_vinculo',
        'uso_imagen', 'uso_imagen_fecha', 'apto_medico_path', 'apto_medico_nombre', 'apto_medico_vence', 'apto_aviso_enviado_el',
    ];

    protected $casts = [
        'uso_imagen' => 'boolean',
        'uso_imagen_fecha' => 'date',
        'apto_medico_vence' => 'date',
        'apto_aviso_enviado_el' => 'date',
    ];

    /** Días que faltan para que venza el apto (negativo si ya venció); null si no hay apto cargado. */
    public function diasParaVencerApto(): ?int
    {
        return $this->apto_medico_vence ? (int) now()->startOfDay()->diffInDays($this->apto_medico_vence, false) : null;
    }

    /** @return BelongsTo<Persona, $this> */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }
}
