<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mensaje manual a un grupo (toda la escuela, sedes, bloques, un show…). Ver DifusionService.
 *
 * @property array<string, mixed> $filtros
 * @property list<string> $canales
 * @property array<string, int>|null $resultado
 */
class Difusion extends Model
{
    protected $table = 'difusiones';

    public const ESTADOS = [
        'programada' => 'Programada',
        'enviando' => 'Enviando',
        'enviada' => 'Enviada',
        'cancelada' => 'Cancelada',
    ];

    protected $fillable = [
        'titulo', 'mensaje', 'enlace', 'canales', 'filtros', 'estado',
        'programada_para', 'enviada_at', 'destinatarios', 'resultado', 'user_id',
    ];

    protected $casts = [
        'canales' => 'array',
        'filtros' => 'array',
        'resultado' => 'array',
        'programada_para' => 'datetime',
        'enviada_at' => 'datetime',
        'destinatarios' => 'integer',
    ];

    /** @return BelongsTo<User, $this> */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function etiquetaEstado(): string
    {
        return self::ESTADOS[$this->estado] ?? (string) $this->estado;
    }
}
