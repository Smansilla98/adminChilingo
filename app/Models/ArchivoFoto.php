<?php

namespace App\Models;

use App\Domain\Archivo\ArchivoConsultas;
use App\Models\Concerns\Auditable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fotografía del archivo histórico: el original se conserva intacto y la web usa
 * derivados WebP (ver App\Domain\Archivo\ImagenesArchivo).
 */
class ArchivoFoto extends Model
{
    use Auditable;

    public const ESTADOS = [
        'borrador' => 'Borrador',
        'pendiente' => 'En revisión',
        'cambios' => 'Cambios solicitados',
        'rechazada' => 'Rechazada',
        'publicada' => 'Publicada',
        'oculta' => 'Oculta',
    ];

    /** Estados en los que quien la aportó todavía puede corregirla. */
    public const EDITABLES_POR_APORTANTE = ['borrador', 'pendiente', 'cambios'];

    public const TIPOS = [
        'ensayo' => 'Ensayo',
        'show' => 'Show',
        'gira' => 'Gira',
        'evento' => 'Evento',
        'clase' => 'Clase',
        'calle' => 'Calle',
        'backstage' => 'Backstage',
        'retrato' => 'Retrato',
        'documento' => 'Documento (afiche, flyer, recorte…)',
        'otro' => 'Otro',
    ];

    public const FUENTES = [
        'archivo_personal' => 'Archivo personal',
        'archivo_chilinga' => 'Archivo La Chilinga',
        'fotografo' => 'Fotógrafo/a',
        'coleccion' => 'Colección',
        'documento' => 'Documento o publicación',
        'redes_sociales' => 'Redes sociales',
        'otro' => 'Otro',
    ];

    public const PRECISIONES = [
        'dia' => 'Fecha exacta',
        'mes' => 'Mes y año',
        'anio' => 'Año',
        'aprox' => 'Año aproximado',
    ];

    /** Anchos de los derivados WebP. */
    public const ANCHOS = [400, 800, 1200, 2048];

    protected static function booted(): void
    {
        // La línea de tiempo pública se cachea unos minutos; cualquier cambio la renueva.
        static::saved(fn () => ArchivoConsultas::olvidarCache());
        static::deleted(fn () => ArchivoConsultas::olvidarCache());
    }

    protected $table = 'archivo_fotos';

    protected $fillable = [
        'titulo', 'slug', 'descripcion', 'contexto', 'notas_aportante', 'alt_text', 'tipo',
        'fecha', 'anio', 'mes', 'precision', 'lugar', 'ciudad', 'pais', 'latitud', 'longitud',
        'fotografo', 'fuente', 'fuente_detalle', 'credito', 'licencia', 'aportada_por', 'mostrar_aportante',
        'capitulo_id', 'acontecimiento_id', 'sede_id', 'destacada', 'orden',
        'estado', 'enviada_at', 'revisada_por', 'revisada_at', 'notas_revision', 'motivo_rechazo', 'publicada_at',
        'path', 'nombre_original', 'mime', 'bytes', 'ancho', 'alto', 'hash', 'exif', 'derivados', 'placeholder', 'color',
    ];

    /** Fuera de la auditoría: metadatos técnicos voluminosos. */
    protected $hidden = ['exif', 'derivados', 'placeholder'];

    protected $casts = [
        'fecha' => 'date',
        'anio' => 'integer',
        'mes' => 'integer',
        'latitud' => 'float',
        'longitud' => 'float',
        'mostrar_aportante' => 'boolean',
        'destacada' => 'boolean',
        'orden' => 'integer',
        'enviada_at' => 'datetime',
        'revisada_at' => 'datetime',
        'publicada_at' => 'datetime',
        'bytes' => 'integer',
        'ancho' => 'integer',
        'alto' => 'integer',
        'exif' => 'array',
        'derivados' => 'array',
    ];

    public function capitulo(): BelongsTo
    {
        return $this->belongsTo(ArchivoCapitulo::class, 'capitulo_id');
    }

    public function acontecimiento(): BelongsTo
    {
        return $this->belongsTo(ArchivoAcontecimiento::class, 'acontecimiento_id');
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function aportante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aportada_por');
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisada_por');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(BibliotecaTag::class, 'archivo_foto_tag', 'archivo_foto_id', 'biblioteca_tag_id');
    }

    public function personas(): HasMany
    {
        return $this->hasMany(ArchivoFotoPersona::class, 'archivo_foto_id')->orderBy('orden')->orderBy('id');
    }

    public function revisiones(): HasMany
    {
        return $this->hasMany(ArchivoRevision::class, 'archivo_foto_id')->latest('id');
    }

    public function scopePublicadas(Builder $q): Builder
    {
        return $q->where('estado', 'publicada');
    }

    public function scopeCronologico(Builder $q): Builder
    {
        return $q->orderByRaw('anio IS NULL')->orderBy('anio')->orderByRaw('fecha IS NULL')->orderBy('fecha')->orderBy('orden')->orderBy('id');
    }

    public function esPublica(): bool
    {
        return $this->estado === 'publicada';
    }

    public function esVertical(): bool
    {
        return $this->ancho && $this->alto && $this->alto > $this->ancho * 1.1;
    }

    public function tituloVisible(): string
    {
        return $this->titulo ?: ($this->acontecimiento?->titulo ?? 'Fotografía sin título');
    }

    public function textoAlternativo(): string
    {
        return $this->alt_text ?: trim($this->tituloVisible().($this->anio ? ', '.$this->anio : ''));
    }

    public function fechaLegible(): ?string
    {
        return self::formatearFecha($this->fecha, $this->anio, $this->mes, $this->precision);
    }

    public function decada(): ?int
    {
        return $this->anio ? intdiv($this->anio, 10) * 10 : null;
    }

    /** Nombre del aportante (solo aportes de la comunidad) si dio permiso para mostrarlo. */
    public function aportanteVisible(): ?string
    {
        if (! $this->aportada_por || ! $this->enviada_at) {
            return null;
        }

        return $this->mostrar_aportante ? ($this->aportante?->name ?: null) : 'Aportante anónimo';
    }

    public function etiquetaEstado(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    public function etiquetaFuente(): ?string
    {
        return $this->fuente ? (self::FUENTES[$this->fuente] ?? $this->fuente) : null;
    }

    public function etiquetaTipo(): ?string
    {
        return $this->tipo ? (self::TIPOS[$this->tipo] ?? $this->tipo) : null;
    }

    /** URL del derivado más chico que cubra `$ancho` (o el más grande disponible). */
    public function imagenUrl(int $ancho = 1200): string
    {
        return route('archivo.imagen', ['foto' => $this->id, 'ancho' => $this->anchoDisponible($ancho), 'v' => $this->updated_at?->timestamp]);
    }

    public function anchoDisponible(int $ancho): int
    {
        $disponibles = array_map('intval', array_keys($this->derivados ?? []));
        sort($disponibles);
        if ($disponibles === []) {
            return $ancho;
        }
        foreach ($disponibles as $a) {
            if ($a >= $ancho) {
                return $a;
            }
        }

        return end($disponibles);
    }

    public function srcset(): string
    {
        $anchos = array_map('intval', array_keys($this->derivados ?? []));
        sort($anchos);

        return collect($anchos)->map(fn (int $a) => $this->imagenUrl($a).' '.$a.'w')->implode(', ');
    }

    public function url(): string
    {
        return route('archivo.foto', $this->slug);
    }

    public static function formatearFecha(?CarbonInterface $fecha, ?int $anio, ?int $mes, ?string $precision): ?string
    {
        $meses = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        $anio ??= $fecha?->year;
        if (! $anio) {
            return null;
        }

        return match ($precision) {
            'dia' => $fecha ? $fecha->day.' de '.$meses[$fecha->month].' de '.$fecha->year : (string) $anio,
            'mes' => ($mes ?? $fecha?->month) ? ucfirst($meses[$mes ?? $fecha->month]).' de '.$anio : (string) $anio,
            'aprox' => 'Hacia '.$anio,
            default => (string) $anio,
        };
    }
}
