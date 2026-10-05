<?php

namespace App\Models;

use App\Domain\Archivo\ArchivoConsultas;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Capítulo editorial del archivo histórico (Fundación, Primeras giras, 30 años…).
 */
class ArchivoCapitulo extends Model
{
    use Auditable;

    protected static function booted(): void
    {
        // La línea de tiempo pública se cachea unos minutos; cualquier cambio la renueva.
        static::saved(fn () => ArchivoConsultas::olvidarCache());
        static::deleted(fn () => ArchivoConsultas::olvidarCache());
    }

    protected $table = 'archivo_capitulos';

    protected $fillable = [
        'titulo', 'slug', 'bajada', 'descripcion', 'anio_desde', 'anio_hasta',
        'portada_foto_id', 'orden', 'publicado', 'publicado_at',
    ];

    protected $casts = [
        'anio_desde' => 'integer',
        'anio_hasta' => 'integer',
        'orden' => 'integer',
        'publicado' => 'boolean',
        'publicado_at' => 'datetime',
    ];

    public function acontecimientos(): HasMany
    {
        return $this->hasMany(ArchivoAcontecimiento::class, 'capitulo_id')->orderBy('orden')->orderBy('fecha')->orderBy('anio');
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(ArchivoFoto::class, 'capitulo_id');
    }

    public function portada(): BelongsTo
    {
        return $this->belongsTo(ArchivoFoto::class, 'portada_foto_id');
    }

    public function scopePublicados(Builder $q): Builder
    {
        return $q->where('publicado', true);
    }

    public function scopeCronologico(Builder $q): Builder
    {
        return $q->orderByRaw('anio_desde IS NULL')->orderBy('anio_desde')->orderBy('orden')->orderBy('id');
    }

    public function periodo(): ?string
    {
        if (! $this->anio_desde) {
            return null;
        }
        if (! $this->anio_hasta || $this->anio_hasta === $this->anio_desde) {
            return (string) $this->anio_desde;
        }

        return $this->anio_desde.' — '.$this->anio_hasta;
    }

    public function url(): string
    {
        return route('archivo.capitulo', $this->slug);
    }
}
