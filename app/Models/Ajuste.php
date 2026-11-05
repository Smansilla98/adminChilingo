<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Ajuste editable desde Configuración: una clave con un valor JSON.
 *
 * @property string $clave
 * @property array<string, mixed> $valor
 */
class Ajuste extends Model
{
    protected $table = 'ajustes';

    protected $fillable = ['clave', 'valor', 'actualizado_por'];

    protected $casts = ['valor' => 'array'];

    /** @return array<string, mixed> */
    public static function leer(string $clave): array
    {
        return Cache::remember("ajuste:{$clave}", 300, function () use ($clave) {
            if (! Schema::hasTable('ajustes')) {
                return [];
            }

            return (array) (self::query()->where('clave', $clave)->value('valor') ?? []);
        });
    }

    /** @param  array<string, mixed>  $valor */
    public static function guardar(string $clave, array $valor, ?int $por = null): void
    {
        self::query()->updateOrCreate(['clave' => $clave], ['valor' => $valor, 'actualizado_por' => $por]);
        Cache::forget("ajuste:{$clave}");
    }
}
