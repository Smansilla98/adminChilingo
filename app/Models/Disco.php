<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Disco de la banda de La Chilinga.
 *
 * Los JSON vienen de la base (o de una edición a mano): se leen sin confiar en su forma.
 *
 * @property array<int, mixed>|null $datos
 * @property array<int, mixed>|null $temas
 * @property array<int, mixed>|null $enlaces
 * @property array<int, mixed>|null $fuentes
 */
class Disco extends Model
{
    protected $table = 'discos';

    protected $fillable = [
        'slug', 'titulo', 'titulo_alternativo', 'anio', 'nota_anio', 'color', 'descripcion',
        'datos', 'temas', 'nota_temas', 'enlaces', 'fuentes', 'portada_path', 'publicado',
    ];

    protected $casts = [
        'anio' => 'integer',
        'datos' => 'array',
        'temas' => 'array',
        'enlaces' => 'array',
        'fuentes' => 'array',
        'publicado' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @param  Builder<Disco>  $q */
    public function scopePublicados(Builder $q): void
    {
        $q->where('publicado', true)->orderBy('anio')->orderBy('id');
    }

    /** @return list<array{titulo: string, duracion: string|null}> */
    public function listaDeTemas(): array
    {
        return array_values(array_filter(
            is_array($this->temas) ? $this->temas : [],
            fn ($t) => is_array($t) && filled($t['titulo'] ?? null)
        ));
    }

    /** Duración total ("31:08"), solo si todos los temas la tienen. */
    public function duracionTotal(): ?string
    {
        $temas = $this->listaDeTemas();
        if ($temas === []) {
            return null;
        }
        $segundos = 0;
        foreach ($temas as $t) {
            $s = self::segundos($t['duracion'] ?? null);
            if ($s === null) {
                return null;
            }
            $segundos += $s;
        }

        return intdiv($segundos, 60).':'.str_pad((string) ($segundos % 60), 2, '0', STR_PAD_LEFT);
    }

    public static function segundos(?string $duracion): ?int
    {
        if (! is_string($duracion) || ! preg_match('/^(\d{1,2}):([0-5]\d)$/', trim($duracion), $m)) {
            return null;
        }

        return (int) $m[1] * 60 + (int) $m[2];
    }

    /**
     * Dónde escucharlo: los enlaces cargados y, si no hay de una plataforma, una
     * búsqueda (así nunca se enlaza a un álbum equivocado).
     *
     * @return list<array{etiqueta: string, url: string, busqueda: bool}>
     */
    public function dondeEscuchar(): array
    {
        $enlaces = collect(is_array($this->enlaces) ? $this->enlaces : [])
            ->filter(fn ($e) => is_array($e) && filled($e['url'] ?? null))
            ->map(fn ($e) => ['etiqueta' => (string) ($e['etiqueta'] ?? 'Escuchar'), 'url' => (string) $e['url'], 'busqueda' => false])
            ->values();
        $consulta = rawurlencode('La Chilinga '.$this->titulo);
        foreach (['Spotify' => "https://open.spotify.com/search/{$consulta}", 'YouTube' => "https://www.youtube.com/results?search_query={$consulta}"] as $plataforma => $url) {
            if (! $enlaces->contains(fn ($e) => Str::contains(mb_strtolower($e['etiqueta'].' '.$e['url']), mb_strtolower($plataforma)))) {
                $enlaces->push(['etiqueta' => "Buscar en {$plataforma}", 'url' => $url, 'busqueda' => true]);
            }
        }

        return $enlaces->all();
    }

    /**
     * Toques del programa que suenan en el disco, por tema (clave: índice del tema).
     * Compara nombres sin acentos, mayúsculas ni numeración final ("Malamakuá" = "Malamakua I").
     *
     * @param  Collection<int, ProgramaRitmo>  $toques
     * @return array<int, ProgramaRitmo>
     */
    public function toquesDelPrograma(Collection $toques): array
    {
        // Un tema sin número se cruza con el toque del mismo nombre o con su parte I
        // ("Malamakuá" = "Malamakua I"), nunca con la II.
        $porNombre = $toques->filter(fn (ProgramaRitmo $t) => filled($t->slug)
            && ! preg_match('/\s+(ii|iii|iv|v|[2-9])$/', Str::lower(Str::ascii(trim($t->nombre)))))
            ->groupBy(fn (ProgramaRitmo $t) => self::claveNombre($t->nombre));
        $out = [];
        foreach ($this->listaDeTemas() as $i => $tema) {
            $exacto = $toques->first(fn (ProgramaRitmo $t) => filled($t->slug) && Str::lower(Str::ascii($t->nombre)) === Str::lower(Str::ascii($tema['titulo'])));
            // Un tema numerado ("Muñequitos II") solo coincide con su mismo número.
            $numerado = self::claveNombre($tema['titulo']) !== (string) preg_replace('/[^a-z0-9]+/', '', Str::lower(Str::ascii($tema['titulo'])));
            $toque = $exacto ?? ($numerado ? null : $porNombre->get(self::claveNombre($tema['titulo']))?->first());
            if ($toque) {
                $out[$i] = $toque;
            }
        }

        return $out;
    }

    public static function claveNombre(string $nombre): string
    {
        $n = Str::lower(Str::ascii($nombre));
        $n = (string) preg_replace('/\s+(i|ii|iii|iv|v|\d)$/', '', trim($n));

        return (string) preg_replace('/[^a-z0-9]+/', '', $n);
    }

    /** "Percusión (1998)". */
    public function etiqueta(): string
    {
        return "{$this->titulo} ({$this->anio})";
    }
}
