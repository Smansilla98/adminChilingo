<?php

namespace App\Domain\Archivo;

use App\Models\ArchivoAcontecimiento;
use App\Models\ArchivoCapitulo;
use App\Models\ArchivoFoto;
use App\Models\ArchivoFotoPersona;

/**
 * Forma pública de fotos, acontecimientos y capítulos: la usan el visor web (JSON
 * embebido) y la API. Nunca expone la ruta del original, el EXIF completo ni datos
 * de las personas más allá del nombre.
 */
class PresentadorArchivo
{
    /** @return array<string, mixed> */
    public static function foto(ArchivoFoto $f, bool $detalle = false): array
    {
        $datos = [
            'id' => $f->id,
            'slug' => $f->slug,
            'titulo' => $f->tituloVisible(),
            'anio' => $f->anio,
            'fecha' => $f->fechaLegible(),
            'descripcion' => $f->descripcion,
            'alt' => $f->textoAlternativo(),
            'ancho' => $f->ancho,
            'alto' => $f->alto,
            'color' => $f->color,
            'placeholder' => $f->placeholder,
            'imagen' => [
                'chica' => $f->imagenUrl(400),
                'media' => $f->imagenUrl(800),
                'grande' => $f->imagenUrl(1200),
                'completa' => $f->imagenUrl(2048),
                'srcset' => $f->srcset(),
            ],
            'url' => $f->esPublica() ? $f->url() : null,
            'acontecimiento' => $f->relationLoaded('acontecimiento') && $f->acontecimiento
                ? ['titulo' => $f->acontecimiento->titulo, 'slug' => $f->acontecimiento->slug, 'url' => $f->acontecimiento->publicado ? $f->acontecimiento->url() : null]
                : null,
            'sede' => $f->relationLoaded('sede') ? $f->sede?->nombre : null,
        ];
        if (! $detalle) {
            return $datos;
        }

        return $datos + [
            'contexto' => $f->contexto,
            'tipo' => $f->etiquetaTipo(),
            'lugar' => collect([$f->lugar, $f->ciudad, $f->pais])->filter()->implode(', ') ?: null,
            'fotografo' => $f->fotografo,
            'fuente' => $f->etiquetaFuente(),
            'fuente_detalle' => $f->fuente_detalle,
            'credito' => $f->credito,
            'licencia' => $f->licencia,
            'aportante' => $f->aportanteVisible(),
            'personas' => $f->personas->map(fn (ArchivoFotoPersona $p) => [
                'nombre' => $p->nombreVisible(),
                'detalle' => $p->detalle,
                'clave' => $p->persona_id ? (string) $p->persona_id : $p->nombre,
            ])->values()->all(),
            'tags' => $f->tags->map(fn ($t) => ['nombre' => $t->nombre, 'slug' => $t->slug])->values()->all(),
            'capitulo' => $f->capitulo && $f->capitulo->publicado ? ['titulo' => $f->capitulo->titulo, 'url' => $f->capitulo->url()] : null,
            'camara' => collect([$f->exif['Make'] ?? null, $f->exif['Model'] ?? null])->filter()->unique()->implode(' ') ?: null,
        ];
    }

    /** @return array<string, mixed> */
    public static function acontecimiento(ArchivoAcontecimiento $a, bool $detalle = false): array
    {
        $datos = [
            'id' => $a->id,
            'slug' => $a->slug,
            'titulo' => $a->titulo,
            'anio' => $a->anio,
            'fecha' => $a->fechaLegible(),
            'bajada' => $a->bajada,
            'descripcion' => $a->descripcion,
            'lugar' => collect([$a->lugar, $a->ciudad, $a->pais])->filter()->implode(', ') ?: null,
            'portada' => $a->portada?->esPublica() ? self::foto($a->portada) : null,
            'url' => $a->publicado ? $a->url() : null,
        ];
        if (! $detalle) {
            return $datos;
        }

        return $datos + [
            'relato' => $a->relato,
            'coordenadas' => $a->latitud !== null && $a->longitud !== null ? ['lat' => $a->latitud, 'lng' => $a->longitud] : null,
            'capitulo' => $a->capitulo && $a->capitulo->publicado ? ['titulo' => $a->capitulo->titulo, 'slug' => $a->capitulo->slug] : null,
            'sede' => $a->sede?->nombre,
            'fotos' => $a->fotos->filter(fn ($f) => $f->esPublica())->map(fn ($f) => self::foto($f))->values()->all(),
            'relacionados' => $a->relacionados->where('publicado', true)->map(fn ($r) => self::acontecimiento($r))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    public static function capitulo(ArchivoCapitulo $c): array
    {
        return [
            'id' => $c->id,
            'slug' => $c->slug,
            'titulo' => $c->titulo,
            'bajada' => $c->bajada,
            'descripcion' => $c->descripcion,
            'periodo' => $c->periodo(),
            'anio_desde' => $c->anio_desde,
            'anio_hasta' => $c->anio_hasta,
            'portada' => $c->portada?->esPublica() ? self::foto($c->portada) : null,
            'url' => $c->publicado ? $c->url() : null,
        ];
    }
}
