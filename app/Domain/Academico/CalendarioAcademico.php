<?php

namespace App\Domain\Academico;

use App\Models\CicloLectivo;

/**
 * Calendario académico de un ciclo, mes por mes (marzo N a febrero N+1).
 *
 * Cada mes tiene una lista de actividades. Marzo a diciembre son clases; enero y febrero son
 * taller de verano y, en paralelo, la gira a Villa Gesell (config chilinga.calendario_academico).
 * Las cuotas siguen cobrándose todos los meses del ciclo.
 */
final class CalendarioAcademico
{
    public const CLASES = 'clases';

    public const TALLER_VERANO = 'taller_verano';

    public const GIRA_GESELL = 'gira_gesell';

    /** @var array<int, string> */
    private const NOMBRES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
        7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    /**
     * @return list<array{anio: int, mes: int, nombre: string, actividades: list<array{tipo: string, etiqueta: string}>, cuotas: bool}>
     */
    public static function para(CicloLectivo $ciclo): array
    {
        $config = config('chilinga.calendario_academico', []);
        $salida = [];
        foreach (CicloLectivo::periodoCuotas((int) $ciclo->anio) as ['anio' => $anio, 'mes' => $mes]) {
            $salida[] = [
                'anio' => $anio,
                'mes' => $mes,
                'nombre' => self::NOMBRES[$mes].' '.$anio,
                'actividades' => self::actividadesDelMes($mes, $config),
                'cuotas' => true,
            ];
        }

        return $salida;
    }

    /**
     * Actividades de un mes. Sin configuración para ese mes, es solo clases.
     *
     * @param  array<int|string, list<string>>  $config  mes (1-12) => tipos
     * @return list<array{tipo: string, etiqueta: string}>
     */
    public static function actividadesDelMes(int $mes, array $config): array
    {
        $tipos = $config[$mes] ?? [self::CLASES];

        return array_map(fn (string $tipo) => ['tipo' => $tipo, 'etiqueta' => self::etiqueta($tipo)], $tipos);
    }

    public static function etiqueta(string $tipo): string
    {
        return match ($tipo) {
            self::TALLER_VERANO => 'Taller de verano',
            self::GIRA_GESELL => 'Gira a Villa Gesell',
            default => 'Clases',
        };
    }
}
