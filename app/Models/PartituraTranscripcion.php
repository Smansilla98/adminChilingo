<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pedido de transcripción automática de una grabación.
 *
 * @property int $id
 * @property int $programa_ritmo_id
 * @property int|null $user_id
 * @property string $estado pendiente|procesando|listo|error
 * @property int $progreso
 * @property string $audio_path
 * @property array<string, mixed>|null $resultado
 * @property array<string, mixed>|null $borrador
 * @property string|null $error
 * @property string|null $autor
 */
class PartituraTranscripcion extends Model
{
    public const PENDIENTE = 'pendiente';

    public const PROCESANDO = 'procesando';

    public const LISTO = 'listo';

    public const ERROR = 'error';

    protected $table = 'partitura_transcripciones';

    protected $fillable = ['programa_ritmo_id', 'user_id', 'estado', 'progreso', 'audio_path', 'resultado', 'borrador', 'error', 'autor'];

    protected $casts = [
        'resultado' => 'array',
        'borrador' => 'array',
        'progreso' => 'integer',
    ];

    /** @return BelongsTo<ProgramaRitmo, $this> */
    public function programaRitmo(): BelongsTo
    {
        return $this->belongsTo(ProgramaRitmo::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function terminada(): bool
    {
        return in_array($this->estado, [self::LISTO, self::ERROR], true);
    }
}
