<?php

namespace App\Support;

class ProgramaRitmoMedios
{
    /** @var array<string, string> */
    public const VIDEOS_BASE = [
        'surdo_grave' => 'Surdo Grave',
        'surdo_agudo' => 'Surdo Agudo',
        'surdo_medio' => 'Surdo Medio',
        'redoblante' => 'Redoblante',
        'timbal' => 'Timbal',
        'repique' => 'Repique',
    ];

    /** @var array<string, string> */
    public const INSTRUMENTOS_OPCIONALES = [
        'agogo' => 'Agogó',
        'palmas' => 'Palmas',
    ];

    /** @var array<string, string> */
    public const VIDEOS_GRUPO = [
        'ensamble' => 'Ensamble completo',
        'llamada_inicio' => 'Llamada del toque',
        'llamada_fin' => 'Llamada final',
    ];

    /** @var array<string, string> */
    public const TIPOS_RECURSO = [
        'imagen' => 'Imagen',
        'pdf' => 'PDF / documento',
        'video' => 'Video (enlace)',
        'enlace' => 'Enlace web',
        'texto' => 'Bloque de texto',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function estructuraVacia(): array
    {
        $bases = [];
        foreach (array_keys(self::VIDEOS_BASE) as $k) {
            $bases[$k] = ['url' => null];
        }
        $grupo = [];
        foreach (array_keys(self::VIDEOS_GRUPO) as $k) {
            $grupo[$k] = ['url' => null];
        }

        return [
            'partitura' => null,
            'partitura_flat' => null,
            'partitura_score' => null,
            'videos_base' => $bases,
            'videos_grupo' => $grupo,
            'cortes' => [],
            'recursos' => [],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $medios
     * @return array<string, mixed>
     */
    public static function normalizar(?array $medios): array
    {
        $out = self::estructuraVacia();
        if (! is_array($medios)) {
            return $out;
        }

        if (! empty($medios['partitura']) && is_array($medios['partitura'])) {
            $out['partitura'] = [
                'path' => $medios['partitura']['path'] ?? null,
                'nombre' => $medios['partitura']['nombre'] ?? null,
            ];
        }

        $out['partitura_score'] = PartituraScore::normalizar($medios['partitura_score'] ?? null);
        $out['partitura_flat'] = self::normalizarPartituraFlat($medios['partitura_flat'] ?? null);

        if (isset($medios['videos_base']) && is_array($medios['videos_base'])) {
            foreach (array_keys(self::VIDEOS_BASE) as $k) {
                $out['videos_base'][$k]['url'] = self::limpiarUrl($medios['videos_base'][$k]['url'] ?? null);
            }
            foreach (['medio' => 'surdo_medio', 'fondo_grave' => 'surdo_grave', 'fondo_agudo' => 'surdo_agudo'] as $legacy => $nuevo) {
                if (empty($out['videos_base'][$nuevo]['url']) && ! empty($medios['videos_base'][$legacy]['url'])) {
                    $out['videos_base'][$nuevo]['url'] = self::limpiarUrl($medios['videos_base'][$legacy]['url']);
                }
            }
        }

        if (isset($medios['videos_grupo']) && is_array($medios['videos_grupo'])) {
            foreach (array_keys(self::VIDEOS_GRUPO) as $k) {
                $out['videos_grupo'][$k]['url'] = self::limpiarUrl($medios['videos_grupo'][$k]['url'] ?? null);
            }
        }

        if (isset($medios['cortes']) && is_array($medios['cortes'])) {
            foreach ($medios['cortes'] as $c) {
                if (! is_array($c)) {
                    continue;
                }
                $item = [
                    'titulo' => trim((string) ($c['titulo'] ?? '')),
                    'url' => self::limpiarUrl($c['url'] ?? null),
                    'path' => $c['path'] ?? null,
                    'nombre' => $c['nombre'] ?? null,
                ];
                if ($item['titulo'] !== '' || $item['url'] || $item['path']) {
                    $out['cortes'][] = $item;
                }
            }
        }

        if (isset($medios['recursos']) && is_array($medios['recursos'])) {
            foreach ($medios['recursos'] as $r) {
                if (! is_array($r)) {
                    continue;
                }
                $tipo = (string) ($r['tipo'] ?? 'enlace');
                if (! array_key_exists($tipo, self::TIPOS_RECURSO)) {
                    $tipo = 'enlace';
                }
                $item = [
                    'tipo' => $tipo,
                    'titulo' => trim((string) ($r['titulo'] ?? '')),
                    'url' => self::limpiarUrl($r['url'] ?? null),
                    'path' => $r['path'] ?? null,
                    'nombre' => $r['nombre'] ?? null,
                    'contenido' => trim((string) ($r['contenido'] ?? '')),
                ];
                if ($item['titulo'] !== '' || $item['url'] || $item['path'] || $item['contenido'] !== '') {
                    $out['recursos'][] = $item;
                }
            }
        }

        return $out;
    }

    /**
     * @return array{musicxml: string, updated_at: string|null}|null
     */
    public static function normalizarPartituraFlat(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }

        $xml = trim((string) ($raw['musicxml'] ?? ''));
        if ($xml === '' || (! str_contains($xml, '<score-partwise') && ! str_contains($xml, '<score-timewise'))) {
            return null;
        }

        $updatedAt = $raw['updated_at'] ?? null;

        return [
            'musicxml' => $xml,
            'updated_at' => is_string($updatedAt) && $updatedAt !== '' ? $updatedAt : null,
        ];
    }

    public static function limpiarUrl(mixed $url): ?string
    {
        $u = trim((string) $url);

        return $u !== '' ? $u : null;
    }

    /**
     * Convierte URL de YouTube/Vimeo a embed si aplica.
     */
    public static function urlEmbed(?string $url): ?string
    {
        $url = self::limpiarUrl($url);
        if (! $url) {
            return null;
        }

        if (str_contains($url, 'youtube.com/embed/') || str_contains($url, 'player.vimeo.com')) {
            return $url;
        }

        if (preg_match('~(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/shorts/)([a-zA-Z0-9_-]{6,})~', $url, $m)) {
            return 'https://www.youtube.com/embed/'.$m[1];
        }

        if (preg_match('~vimeo\.com/(\d+)~', $url, $m)) {
            return 'https://player.vimeo.com/video/'.$m[1];
        }

        return null;
    }

    public static function esVideoEmbeddable(?string $url): bool
    {
        return self::urlEmbed($url) !== null;
    }

    /**
     * @param  array<string, mixed>  $medios
     */
    public static function tieneContenidoMultimedia(array $medios): bool
    {
        if (! empty($medios['partitura']['path'])) {
            return true;
        }
        if (! empty($medios['partitura_score']['sections'])) {
            return true;
        }
        if (! empty($medios['partitura_flat']['musicxml'])) {
            return true;
        }
        foreach ($medios['videos_base'] ?? [] as $v) {
            if (! empty($v['url'])) {
                return true;
            }
        }
        foreach ($medios['videos_grupo'] ?? [] as $v) {
            if (! empty($v['url'])) {
                return true;
            }
        }
        foreach ($medios['cortes'] ?? [] as $c) {
            if (! empty($c['url']) || ! empty($c['path'])) {
                return true;
            }
        }
        foreach ($medios['recursos'] ?? [] as $r) {
            if (! empty($r['url']) || ! empty($r['path']) || ! empty($r['contenido'])) {
                return true;
            }
        }

        return false;
    }
}
