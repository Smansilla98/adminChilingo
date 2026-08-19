<?php

namespace App\Support;

class AparienciaTema
{
    /** Tokens editables guardados en users.apariencia_json */
    public const DEFAULTS = [
        'accent' => '#f26422',
        'font_display' => 'Manrope',
        'font_body' => 'Inter',
    ];

    /** @var array<string, array{hex: string, label: string}> */
    public const ACENTOS = [
        'naranja' => ['hex' => '#f26422', 'label' => 'Naranja'],
        'verde' => ['hex' => '#3daf3a', 'label' => 'Verde'],
        'celeste' => ['hex' => '#3ec8ea', 'label' => 'Celeste'],
        'rojo' => ['hex' => '#e31b23', 'label' => 'Rojo'],
        'azul' => ['hex' => '#3e7bfa', 'label' => 'Azul (legado)'],
    ];

    /** @var array<string, array{family: string, google: string, sample: string}> */
    public const FUENTES_TITULO = [
        'Manrope' => [
            'family' => 'Manrope',
            'google' => 'Manrope:wght@500;600;700;800',
            'sample' => 'Tablero de la escuela',
        ],
        'Space Grotesk' => [
            'family' => 'Space Grotesk',
            'google' => 'Space+Grotesk:wght@500;600;700',
            'sample' => 'Tablero de la escuela',
        ],
        'Sora' => [
            'family' => 'Sora',
            'google' => 'Sora:wght@500;600;700;800',
            'sample' => 'Tablero de la escuela',
        ],
        'Outfit' => [
            'family' => 'Outfit',
            'google' => 'Outfit:wght@500;600;700;800',
            'sample' => 'Tablero de la escuela',
        ],
        'DM Sans' => [
            'family' => 'DM Sans',
            'google' => 'DM+Sans:wght@500;600;700',
            'sample' => 'Tablero de la escuela',
        ],
    ];

    /** @var array<string, array{family: string, google: string, sample: string}> */
    public const FUENTES_CUERPO = [
        'Inter' => [
            'family' => 'Inter',
            'google' => 'Inter:wght@400;500;600;700',
            'sample' => 'Texto de apoyo y formularios del sistema.',
        ],
        'Source Sans 3' => [
            'family' => 'Source Sans 3',
            'google' => 'Source+Sans+3:wght@400;500;600;700',
            'sample' => 'Texto de apoyo y formularios del sistema.',
        ],
        'Nunito Sans' => [
            'family' => 'Nunito Sans',
            'google' => 'Nunito+Sans:wght@400;500;600;700',
            'sample' => 'Texto de apoyo y formularios del sistema.',
        ],
        'IBM Plex Sans' => [
            'family' => 'IBM Plex Sans',
            'google' => 'IBM+Plex+Sans:wght@400;500;600;700',
            'sample' => 'Texto de apoyo y formularios del sistema.',
        ],
        'DM Sans' => [
            'family' => 'DM Sans',
            'google' => 'DM+Sans:wght@400;500;600;700',
            'sample' => 'Texto de apoyo y formularios del sistema.',
        ],
    ];

    /**
     * @param  array<string, mixed>|null  $raw
     * @return array{accent: string, font_display: string, font_body: string}
     */
    public static function normalizar(?array $raw): array
    {
        $out = self::DEFAULTS;
        if (! is_array($raw)) {
            return $out;
        }

        $accent = self::sanitizarHex((string) ($raw['accent'] ?? ''));
        if ($accent !== null) {
            $out['accent'] = $accent;
        }

        $display = (string) ($raw['font_display'] ?? '');
        if (array_key_exists($display, self::FUENTES_TITULO)) {
            $out['font_display'] = $display;
        }

        $body = (string) ($raw['font_body'] ?? '');
        if (array_key_exists($body, self::FUENTES_CUERPO)) {
            $out['font_body'] = $body;
        }

        return $out;
    }

    public static function sanitizarHex(string $hex): ?string
    {
        $hex = trim($hex);
        if ($hex === '') {
            return null;
        }
        if ($hex[0] !== '#') {
            $hex = '#'.$hex;
        }
        if (! preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)) {
            return null;
        }

        return strtolower($hex);
    }

    /**
     * Contraste aproximado: texto sobre el acento (botones).
     */
    public static function textoSobreAcento(string $hex): string
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;
        $luma = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

        return $luma > 0.62 ? '#0a0e1a' : '#ffffff';
    }

    public static function oscurecer(string $hex, float $factor = 0.82): string
    {
        $hex = ltrim($hex, '#');
        $r = (int) round(hexdec(substr($hex, 0, 2)) * $factor);
        $g = (int) round(hexdec(substr($hex, 2, 2)) * $factor);
        $b = (int) round(hexdec(substr($hex, 4, 2)) * $factor);

        return sprintf('#%02x%02x%02x', min(255, $r), min(255, $g), min(255, $b));
    }

    public static function softRgba(string $hex, float $alpha = 0.16): string
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return sprintf('rgba(%d, %d, %d, %.2f)', $r, $g, $b, $alpha);
    }

    /**
     * @param  array{accent: string, font_display: string, font_body: string}  $tema
     */
    public static function cssVariables(array $tema): string
    {
        $tema = self::normalizar($tema);
        $accent = $tema['accent'];
        $hover = self::oscurecer($accent, 0.82);
        $soft = self::softRgba($accent, 0.16);
        $softBtn = self::softRgba($accent, 0.15);
        $onAccent = self::textoSobreAcento($accent);
        $display = $tema['font_display'];
        $body = $tema['font_body'];

        $lines = [
            "--accent: {$accent};",
            "--accent-hover: {$hover};",
            "--accent-soft: {$soft};",
            "--accent-on: {$onAccent};",
            "--blue: {$accent};",
            "--brick: {$accent};",
            "--brick-soft: {$softBtn};",
            "--brass: {$accent};",
            "--brass-soft: {$soft};",
            "--accent2: {$accent};",
            "--font-display: '{$display}', 'Inter', system-ui, sans-serif;",
            "--font-body: '{$body}', system-ui, sans-serif;",
        ];

        return ":root {\n  ".implode("\n  ", $lines)."\n}";
    }

    /**
     * @param  array{accent: string, font_display: string, font_body: string}  $tema
     */
    public static function googleFontsUrl(array $tema): string
    {
        $tema = self::normalizar($tema);
        $families = [];
        $d = self::FUENTES_TITULO[$tema['font_display']]['google'] ?? null;
        $b = self::FUENTES_CUERPO[$tema['font_body']]['google'] ?? null;
        if ($d) {
            $families[] = 'family='.$d;
        }
        if ($b && $b !== $d) {
            $families[] = 'family='.$b;
        }
        // Mono siempre disponible
        $families[] = 'family=JetBrains+Mono:wght@400;600';

        return 'https://fonts.googleapis.com/css2?'.implode('&', $families).'&display=swap';
    }

    /**
     * URL con todas las fuentes curadas (pantalla Apariencia / preview).
     */
    public static function googleFontsUrlCompleta(): string
    {
        $seen = [];
        $families = [];
        foreach (array_merge(self::FUENTES_TITULO, self::FUENTES_CUERPO) as $meta) {
            $g = $meta['google'];
            if (isset($seen[$g])) {
                continue;
            }
            $seen[$g] = true;
            $families[] = 'family='.$g;
        }
        $families[] = 'family=JetBrains+Mono:wght@400;600';

        return 'https://fonts.googleapis.com/css2?'.implode('&', $families).'&display=swap';
    }

    public static function esDefault(array $tema): bool
    {
        $tema = self::normalizar($tema);

        return $tema === self::DEFAULTS;
    }
}
