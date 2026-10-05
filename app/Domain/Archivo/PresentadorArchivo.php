<?php

namespace App\Domain\Archivo;

use App\Models\ArchivoAcontecimiento;
use App\Models\ArchivoCapitulo;
use App\Models\ArchivoFoto;
use App\Models\ArchivoFotoPersona;
use App\Models\User;

/**
 * Forma pública de fotos, acontecimientos y capítulos: la usan el visor web (JSON
 * embebido) y la API. Nunca expone la ruta del original, el EXIF completo ni datos
 * de las personas más allá del nombre.
 */
class PresentadorArchivo
{
    /**
     * Con `$api`, lo no publicado se sirve por /api/v1 (token) en lugar de la ruta web (sesión).
     *
     * @return array<string, mixed>
     */
    public static function foto(ArchivoFoto $f, bool $detalle = false, bool $api = false): array
    {
        $url = fn (int $ancho) => $api && ! $f->esPublica()
            ? route('api.v1.archivo.imagen', ['foto' => $f->id, 'ancho' => $f->anchoDisponible($ancho), 'v' => $f->updated_at?->timestamp])
            : $f->imagenUrl($ancho);

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
                'chica' => $url(400),
                'media' => $url(800),
                'grande' => $url(1200),
                'completa' => $url(2048),
                'srcset' => $api && ! $f->esPublica() ? null : $f->srcset(),
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

    /**
     * Ficha completa para quien gestiona o aportó la foto: estado, procedencia, vínculos
     * editables (ids) y qué acciones permite la Policy.
     *
     * @return array<string, mixed>
     */
    public static function fotoGestion(ArchivoFoto $f, User $user): array
    {
        return self::foto($f, true, true) + [
            'titulo_original' => $f->titulo,
            'estado' => $f->estado,
            'estado_etiqueta' => $f->etiquetaEstado(),
            'tipo_clave' => $f->tipo,
            'fuente_clave' => $f->fuente,
            'precision' => $f->precision,
            'fecha_iso' => $f->fecha?->toDateString(),
            'mes' => $f->mes,
            'lugar_detalle' => ['lugar' => $f->lugar, 'ciudad' => $f->ciudad, 'pais' => $f->pais, 'latitud' => $f->latitud, 'longitud' => $f->longitud],
            'alt_text' => $f->alt_text,
            'notas_aportante' => $f->notas_aportante,
            'sede_id' => $f->sede_id,
            'capitulo_id' => $f->capitulo_id,
            'acontecimiento_id' => $f->acontecimiento_id,
            'destacada' => $f->destacada,
            'orden' => $f->orden,
            'mostrar_aportante' => $f->mostrar_aportante,
            'aportante_nombre' => $f->aportante?->name,
            'es_aporte' => $f->enviada_at !== null,
            'enviada_at' => $f->enviada_at?->toIso8601String(),
            'revisada_at' => $f->revisada_at?->toIso8601String(),
            'notas_revision' => $f->notas_revision,
            'motivo_rechazo' => $f->motivo_rechazo,
            'personas_editables' => $f->personas->map(fn (ArchivoFotoPersona $p) => ['persona_id' => $p->persona_id, 'nombre' => $p->nombreVisible(), 'detalle' => $p->detalle])->values()->all(),
            'tecnico' => ['nombre_original' => $f->nombre_original, 'mime' => $f->mime, 'bytes' => $f->bytes, 'ancho' => $f->ancho, 'alto' => $f->alto, 'exif' => $f->exif],
            'revisiones' => $f->relationLoaded('revisiones') ? $f->revisiones->map(fn ($r) => [
                'accion' => $r->accion, 'etiqueta' => $r->etiqueta(), 'notas' => $r->notas,
                'fecha' => $r->created_at?->toIso8601String(), 'usuario' => $r->user?->name,
            ])->values()->all() : [],
            'acciones' => [
                'editar' => $user->can('update', $f),
                'editar_equipo' => $user->can('editarComoEquipo', $f),
                'enviar' => $user->can('enviar', $f),
                'moderar' => $user->can('moderate', $f),
                'publicar' => $user->can('publish', $f),
                'eliminar' => $user->can('delete', $f),
            ],
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
