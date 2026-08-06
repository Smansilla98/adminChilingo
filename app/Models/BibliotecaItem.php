<?php

namespace App\Models;

use App\Support\ProgramaRitmoMedios;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class BibliotecaItem extends Model
{
    public const TIPOS = [
        'imagen' => 'Imagen',
        'video' => 'Video',
        'audio' => 'Audio',
        'pdf' => 'PDF',
        'enlace' => 'Enlace',
        'otro' => 'Otro',
    ];

    protected $fillable = [
        'titulo',
        'descripcion',
        'tipo',
        'path',
        'url',
        'mime',
        'nombre_original',
        'bytes',
        'autor_nombre',
        'estado',
        'ip',
        'programa_ritmo_id',
        'instrumento',
    ];

    protected $casts = [
        'bytes' => 'integer',
        'programa_ritmo_id' => 'integer',
    ];

    /**
     * Instrumentos / roles asociados a un aporte (mismas claves del programa).
     *
     * @return array<string, string>
     */
    public static function instrumentosOpciones(): array
    {
        return array_merge(
            ProgramaRitmoMedios::VIDEOS_BASE,
            ProgramaRitmoMedios::INSTRUMENTOS_OPCIONALES,
            ProgramaRitmoMedios::VIDEOS_GRUPO,
        );
    }

    public static function etiquetaInstrumento(?string $clave): ?string
    {
        if ($clave === null || $clave === '') {
            return null;
        }

        return self::instrumentosOpciones()[$clave] ?? $clave;
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(BibliotecaTag::class, 'biblioteca_item_tag');
    }

    public function toque(): BelongsTo
    {
        return $this->belongsTo(ProgramaRitmo::class, 'programa_ritmo_id');
    }

    public function scopePublicados(Builder $q): Builder
    {
        return $q->where('estado', 'publicado');
    }

    public function scopeDeToque(Builder $q, int|ProgramaRitmo $toque): Builder
    {
        $id = $toque instanceof ProgramaRitmo ? $toque->id : $toque;

        return $q->where('programa_ritmo_id', $id);
    }

    public function etiquetaToqueInstrumento(): ?string
    {
        $partes = [];
        if ($this->toque) {
            $partes[] = $this->toque->nombre;
        }
        $inst = self::etiquetaInstrumento($this->instrumento);
        if ($inst) {
            $partes[] = $inst;
        }

        return $partes !== [] ? implode(' · ', $partes) : null;
    }

    public function esImagen(): bool
    {
        return $this->tipo === 'imagen' || str_starts_with((string) $this->mime, 'image/');
    }

    public function esVideo(): bool
    {
        return $this->tipo === 'video' || str_starts_with((string) $this->mime, 'video/');
    }

    public function esAudio(): bool
    {
        return $this->tipo === 'audio' || str_starts_with((string) $this->mime, 'audio/');
    }

    public function esPdf(): bool
    {
        return $this->tipo === 'pdf' || $this->mime === 'application/pdf';
    }

    public function esEnlace(): bool
    {
        return $this->tipo === 'enlace' || (! empty($this->url) && empty($this->path));
    }

    public function archivoUrl(): ?string
    {
        if ($this->path) {
            return route('biblioteca.archivo', $this);
        }
        if ($this->url) {
            return $this->url;
        }

        return null;
    }

    public function tieneArchivo(): bool
    {
        return $this->path && Storage::disk('comprobantes')->exists($this->path);
    }

    public static function detectarTipo(?string $mime, ?string $ext, bool $tieneUrl): string
    {
        $mime = strtolower((string) $mime);
        $ext = strtolower((string) $ext);

        if (str_starts_with($mime, 'image/') || in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            return 'imagen';
        }
        if (str_starts_with($mime, 'video/') || in_array($ext, ['mp4', 'webm', 'mov', 'm4v'], true)) {
            return 'video';
        }
        if (str_starts_with($mime, 'audio/') || in_array($ext, ['mp3', 'wav', 'ogg', 'm4a'], true)) {
            return 'audio';
        }
        if ($mime === 'application/pdf' || $ext === 'pdf') {
            return 'pdf';
        }
        if ($tieneUrl) {
            return 'enlace';
        }

        return 'otro';
    }
}
