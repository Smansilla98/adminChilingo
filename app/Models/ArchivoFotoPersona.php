<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Quién aparece en una foto: una persona del sistema o, si no está cargada, un
 * nombre libre. En público solo se muestra el nombre.
 */
class ArchivoFotoPersona extends Model
{
    protected $table = 'archivo_foto_persona';

    protected $fillable = ['archivo_foto_id', 'persona_id', 'nombre', 'detalle', 'orden'];

    /** @return BelongsTo<ArchivoFoto, $this> */
    public function foto(): BelongsTo
    {
        return $this->belongsTo(ArchivoFoto::class, 'archivo_foto_id');
    }

    /** @return BelongsTo<Persona, $this> */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    public function nombreVisible(): string
    {
        return $this->persona?->nombre_completo ?: (string) $this->nombre;
    }
}
