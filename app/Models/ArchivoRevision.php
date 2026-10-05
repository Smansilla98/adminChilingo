<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Paso del historial de moderación de una foto (lo ve también quien la aportó). */
class ArchivoRevision extends Model
{
    public const ACCIONES = [
        'creada' => 'Cargada',
        'enviada' => 'Enviada a revisión',
        'aprobada' => 'Aprobada y publicada',
        'rechazada' => 'Rechazada',
        'cambios' => 'Se pidieron cambios',
        'publicada' => 'Publicada',
        'ocultada' => 'Ocultada',
        'reemplazada' => 'Imagen reemplazada',
    ];

    protected $table = 'archivo_revisiones';

    protected $fillable = ['archivo_foto_id', 'user_id', 'accion', 'estado_anterior', 'estado_nuevo', 'notas'];

    public function foto(): BelongsTo
    {
        return $this->belongsTo(ArchivoFoto::class, 'archivo_foto_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function etiqueta(): string
    {
        return self::ACCIONES[$this->accion] ?? $this->accion;
    }
}
