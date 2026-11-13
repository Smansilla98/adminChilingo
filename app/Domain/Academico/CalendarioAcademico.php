<?php

namespace App\Domain\Academico;

use App\Models\CicloLectivo;

/**
 * Calendario académico de un ciclo, mes por mes (marzo N a febrero N+1).
 *
 * Marzo a diciembre son clases regulares. Enero es taller de verano y febrero, gira a Villa Gesell
 * (config chilinga.calendario_academico). Las cuotas siguen cobrándose todos los meses del ciclo.
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
     * @return list<array{anio: int, mes: int, nombre: string, tipo: string, etiqueta: string, cuotas: bool}>
     */
    public static function para(CicloLectivo $ciclo): array
    {
        $dias = config('chilinga.calendario_academico', []);
        $salida = [];
        foreach (CicloLectivo::periodoCuotas((int) $ciclo->anio) as ['anio' => $anio, 'mes' => $mes]) {
            $tipo = self::tipoDelMes($mes, $dias);
            $salida[] = [
                'anio' => $anio,
                'mes' => $mes,
                'nombre' => self::NOMBRES[$mes].' '.$anio,
                'tipo' => $tipo,
                'etiqueta' => self::etiqueta($tipo),
                'cuotas' => true,
            ];
        }

        return $salida;
    }

    /** @param  array<int|string, string>  $config  mes (1-12) => tipo; las claves numéricas en texto también valen */
    public static function tipoDelMes(int $mes, array $config): string
    {
        return $config[$mes] ?? self::CLASES;
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
