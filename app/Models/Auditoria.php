<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class Auditoria extends Model
{
    protected $table = 'auditoria';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'accion', 'entidad_tipo', 'entidad_id',
        'datos_anteriores', 'datos_nuevos', 'ip', 'user_agent', 'origen', 'created_at',
    ];

    protected $casts = [
        'datos_anteriores' => 'array',
        'datos_nuevos' => 'array',
        'created_at' => 'datetime',
    ];

    /** Permite desactivar la auditoría en procesos masivos (backfill). */
    private static bool $pausada = false;

    public static function sinRegistrar(callable $callback): mixed
    {
        $previo = self::$pausada;
        self::$pausada = true;
        try {
            return $callback();
        } finally {
            self::$pausada = $previo;
        }
    }

    /**
     * @param  array<string, mixed>|null  $antes
     * @param  array<string, mixed>|null  $despues
     */
    public static function registrar(string $accion, Model|string $entidad, ?array $antes = null, ?array $despues = null): void
    {
        if (self::$pausada) {
            return;
        }

        try {
            $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();
            static::query()->create([
                'user_id' => auth()->id(),
                'accion' => $accion,
                'entidad_tipo' => $entidad instanceof Model ? class_basename($entidad) : $entidad,
                'entidad_id' => $entidad instanceof Model ? $entidad->getKey() : null,
                'datos_anteriores' => $antes,
                'datos_nuevos' => $despues,
                'ip' => $request?->ip(),
                'user_agent' => $request ? substr((string) $request->userAgent(), 0, 255) : null,
                'origen' => $request === null ? 'cli' : ($request->is('api/*') ? 'api' : 'web'),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // La auditoría nunca debe impedir la operación de negocio.
            Log::warning('Auditoría: no se pudo registrar', ['accion' => $accion, 'error' => $e->getMessage()]);
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
