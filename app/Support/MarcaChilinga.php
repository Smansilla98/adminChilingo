<?php

namespace App\Support;

/**
 * Manual de marca vigente de La Chilinga (Identidad v3, octubre de 2026).
 * Fuente: resources/marca/identidad-v3.pdf. Ver docs/IDENTIDAD_VISUAL.md.
 */
final class MarcaChilinga
{
    public const VERSION = 'Identidad v3';

    public const MANUAL = 'marca/identidad-v3.pdf';

    /** Paleta base: nombre, hex y para qué se usa según el manual. */
    public const PALETA = [
        ['nombre' => 'Tinta', 'hex' => '#15110E', 'uso' => 'Fondo'],
        ['nombre' => 'Cielo', 'hex' => '#2BA8E0', 'uso' => 'Escuela'],
        ['nombre' => 'Atardecer', 'hex' => '#F26A1B', 'uso' => 'Escuela'],
        ['nombre' => 'Tierra', 'hex' => '#3DAE49', 'uso' => 'Escuela'],
        ['nombre' => 'Hueso', 'hex' => '#F5ECDD', 'uso' => 'Caurí'],
    ];

    /** Color de cada sede: fondo, sombra del patrón y acento del sello y los horarios. */
    public const SEDES = [
        ['sede' => 'Palomar', 'fondo' => '#F26A1B', 'sombra' => '#B84A0C', 'acento' => '#2BA8E0'],
        ['sede' => 'Saavedra', 'fondo' => '#8A5A2B', 'sombra' => '#5E3B1A', 'acento' => '#F26A1B'],
        ['sede' => 'Tacheles', 'fondo' => '#5B2A86', 'sombra' => '#3B1A5A', 'acento' => '#3DAE49'],
        ['sede' => 'Banfield', 'fondo' => '#1F7A4D', 'sombra' => '#145236', 'acento' => '#F26A1B'],
        ['sede' => 'Avellaneda', 'fondo' => '#C8202B', 'sombra' => '#8A141C', 'acento' => '#F5ECDD'],
        ['sede' => 'Sarandí', 'fondo' => '#2BA8E0', 'sombra' => '#1A7AA8', 'acento' => '#F26A1B'],
        ['sede' => 'Quilmes', 'fondo' => '#1D3E8A', 'sombra' => '#12285C', 'acento' => '#3DAE49'],
        ['sede' => 'Varela', 'fondo' => '#3DAE49', 'sombra' => '#257A31', 'acento' => '#F26A1B'],
        ['sede' => 'San Marcos Sierras', 'fondo' => '#B5452A', 'sombra' => '#7E2D1A', 'acento' => '#2BA8E0'],
    ];

    /** Piezas sacadas del manual (public/images/brand/v3). */
    public const LOGOS = [
        'sello-tricolor' => 'Sello tricolor',
        'sello-blanco-negro' => 'Sello blanco y negro',
        'sello-anillo-naranja' => 'Sello anillo naranja',
        'logotipo-hueso' => 'Logotipo claro',
        'logotipo-tinta' => 'Logotipo oscuro',
        'chilingo-hueso' => 'Chilingo claro',
        'chilingo-tinta' => 'Chilingo oscuro',
    ];

    public static function manualPath(): string
    {
        return resource_path(self::MANUAL);
    }
}
