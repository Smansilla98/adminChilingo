<?php

namespace App\Support;

class AparienciaTema
{
    /** Tokens editables guardados en users.apariencia_json */
    public const DEFAULTS = [
        'accent' => '#f26422',
        'font_display' => 'Manrope',
        'font_body' => 'Manrope',
        'tema' => 'claro',
    ];

    /** Tema de color del panel. "sistema" sigue la preferencia del dispositivo. */
    public const TEMAS = [
        'claro' => ['label' => 'Claro', 'icon' => 'bi-sun'],
        'oscuro' => ['label' => 'Oscuro', 'icon' => 'bi-moon-stars'],
        'sistema' => ['label' => 'Según el dispositivo', 'icon' => 'bi-circle-half'],
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
        'Manrope' => [
            'family' => 'Manrope',
            'google' => 'Manrope:wght@400;500;600;700;800',
            'sample' => 'Texto de apoyo y formularios del sistema.',
        ],
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
     * @return array{accent: string, font_display: string, font_body: string, tema: string}
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

        $tema = (string) ($raw['tema'] ?? '');
        if (array_key_exists($tema, self::TEMAS)) {
            $out['tema'] = $tema;
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
     * Variables CSS del acento y las fuentes elegidas. El color de marca se usa tal cual
     * para indicadores; botones y enlaces se ajustan hasta tener contraste AA (4.5:1)
     * en el tema claro y en el oscuro.
     *
     * @param  array<string, string>  $tema
     */
    public static function cssVariables(array $tema): string
    {
        $tema = self::normalizar($tema);
        $accent = $tema['accent'];
        $display = $tema['font_display'];
        $body = $tema['font_body'];

        $primario = self::ajustarContraste($accent, '#ffffff', 4.5, -1);
        $fuerte = self::ajustarContraste($accent, '#ffffff', 4.8, -1);
        $primarioOscuro = self::ajustarContraste($accent, '#111111', 4.5, 1);
        $fuerteOscuro = self::ajustarContraste($accent, '#171a20', 4.5, 1);

        $claro = [
            "--accent: {$accent};",
            '--accent-soft: '.self::softRgba($accent, 0.10).';',
            '--accent-soft-2: '.self::softRgba($accent, 0.18).';',
            "--accent-strong: {$fuerte};",
            "--primary: {$primario};",
            '--primary-hover: '.self::oscurecer($primario, 0.85).';',
            '--primary-on: #ffffff;',
            '--bs-primary-rgb: '.self::rgb($primario).';',
            '--bs-link-color-rgb: '.self::rgb($fuerte).';',
            "--font-display: '{$display}', 'Manrope', system-ui, sans-serif;",
            "--font-body: '{$body}', 'Manrope', system-ui, sans-serif;",
        ];
        $oscuro = [
            "--accent: {$accent};",
            '--accent-soft: '.self::softRgba($accent, 0.14).';',
            '--accent-soft-2: '.self::softRgba($accent, 0.24).';',
            "--accent-strong: {$fuerteOscuro};",
            "--primary: {$primarioOscuro};",
            '--primary-hover: '.self::aclarar($primarioOscuro, 0.12).';',
            '--primary-on: #111111;',
            '--bs-primary-rgb: '.self::rgb($primarioOscuro).';',
            '--bs-link-color-rgb: '.self::rgb($fuerteOscuro).';',
        ];

        return ":root, [data-bs-theme=\"light\"] {\n  ".implode("\n  ", $claro)."\n}\n"
            ."[data-bs-theme=\"dark\"] {\n  ".implode("\n  ", $oscuro)."\n}";
    }

    /** true si el usuario cambió acento o fuentes (el tema claro/oscuro no requiere CSS extra). */
    public static function estiloPersonalizado(array $tema): bool
    {
        $tema = self::normalizar($tema);

        return $tema['accent'] !== self::DEFAULTS['accent']
            || $tema['font_display'] !== self::DEFAULTS['font_display']
            || $tema['font_body'] !== self::DEFAULTS['font_body'];
    }

    public static function contraste(string $a, string $b): float
    {
        $la = self::luminancia($a);
        $lb = self::luminancia($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /** Oscurece (dir -1) o aclara (dir 1) el color hasta alcanzar el contraste pedido contra $fondo. */
    public static function ajustarContraste(string $hex, string $fondo, float $minimo, int $dir): string
    {
        $color = $hex;
        for ($i = 0; $i < 40 && self::contraste($color, $fondo) < $minimo; $i++) {
            $color = $dir < 0 ? self::oscurecer($color, 0.94) : self::aclarar($color, 0.08);
        }

        return $color;
    }

    public static function aclarar(string $hex, float $cantidad = 0.1): string
    {
        $hex = ltrim($hex, '#');
        $c = array_map(fn ($i) => hexdec(substr($hex, $i, 2)), [0, 2, 4]);
        $c = array_map(fn ($v) => (int) round($v + (255 - $v) * $cantidad), $c);

        return sprintf('#%02x%02x%02x', ...$c);
    }

    private static function luminancia(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $c = array_map(function ($i) use ($hex) {
            $v = hexdec(substr($hex, $i, 2)) / 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, [0, 2, 4]);

        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    private static function rgb(string $hex): string
    {
        $hex = ltrim($hex, '#');

        return implode(', ', array_map(fn ($i) => hexdec(substr($hex, $i, 2)), [0, 2, 4]));
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

    /** Tema elegido por el usuario autenticado (claro si no eligió o no hay sesión). */
    public static function temaDe(?\App\Models\User $user): string
    {
        if (! $user || ! is_array($user->apariencia_json ?? null)) {
            return self::DEFAULTS['tema'];
        }

        return self::normalizar($user->apariencia_json)['tema'];
    }

    public static function esDefault(array $tema): bool
    {
        $tema = self::normalizar($tema);

        return $tema === self::DEFAULTS;
    }
}
