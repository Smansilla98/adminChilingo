<?php

namespace App\Models;

use App\Domain\Archivo\ArchivoConsultas;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Hecho fechado del archivo histórico. Puede apuntar a un evento o show de la
 * agenda operativa, pero no los reemplaza: es el relato editorial.
 */
class ArchivoAcontecimiento extends Model
{
    use Auditable;

    protected static function booted(): void
    {
        // La línea de tiempo pública se cachea unos minutos; cualquier cambio la renueva.
        static::saved(fn () => ArchivoConsultas::olvidarCache());
        static::deleted(fn () => ArchivoConsultas::olvidarCache());
    }

    protected $table = 'archivo_acontecimientos';

    protected $fillable = [
        'titulo', 'slug', 'fecha', 'anio', 'precision', 'bajada', 'descripcion', 'relato',
        'lugar', 'ciudad', 'pais', 'latitud', 'longitud',
        'capitulo_id', 'sede_id', 'evento_id', 'show_id', 'portada_foto_id', 'orden',
        'publicado', 'publicado_at',
    ];

    protected $casts = [
        'fecha' => 'date',
        'anio' => 'integer',
        'orden' => 'integer',
        'publicado' => 'boolean',
        'publicado_at' => 'datetime',
        'latitud' => 'float',
        'longitud' => 'float',
    ];

    public function capitulo(): BelongsTo
    {
        return $this->belongsTo(ArchivoCapitulo::class, 'capitulo_id');
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    public function portada(): BelongsTo
    {
        return $this->belongsTo(ArchivoFoto::class, 'portada_foto_id');
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(ArchivoFoto::class, 'acontecimiento_id')->orderBy('orden')->orderBy('id');
    }

    public function relacionados(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'archivo_acontecimiento_relacion', 'acontecimiento_id', 'relacionado_id');
    }

    public function scopePublicados(Builder $q): Builder
    {
        return $q->where('publicado', true);
    }

    public function scopeCronologico(Builder $q): Builder
    {
        return $q->orderByRaw('anio IS NULL')->orderBy('anio')->orderByRaw('fecha IS NULL')->orderBy('fecha')->orderBy('orden')->orderBy('id');
    }

    public function fechaLegible(): ?string
    {
        return ArchivoFoto::formatearFecha($this->fecha, $this->anio, $this->fecha?->month, $this->precision);
    }

    public function url(): string
    {
        return route('archivo.acontecimiento', $this->slug);
    }
}
