<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReportesExport implements WithMultipleSheets
{
    /**
     * @param  array{
     *   alumnos_profesor: array<int, array<int, mixed>>,
     *   ingresos_profesor: array<int, array<int, mixed>>,
     *   actividad: array<int, array<int, mixed>>,
     *   alumnos_bloque: array<int, array<int, mixed>>,
     *   financiero_sede: array<int, array<int, mixed>>,
     *   global: array<int, array<int, mixed>>,
     *   meta: array{mes: int, año: int}
     * }  $payload
     */
    public function __construct(private array $payload) {}

    public function sheets(): array
    {
        $mes = $this->payload['meta']['mes'];
        $año = $this->payload['meta']['año'];

        return [
            new ReportesSheet('Alumnos x profesor', array_merge(
                [['Periodo', "{$mes}/{$año}"]],
                [['Profesor', 'Sedes', 'Bloques', 'Alumnos']],
                $this->payload['alumnos_profesor']
            )),
            new ReportesSheet('Ingresos x profesor', array_merge(
                [['Periodo', "{$mes}/{$año}"]],
                [['Profesor', 'Alumnos', 'Emitido', 'Cobrado', '% cobrado']],
                $this->payload['ingresos_profesor']
            )),
            new ReportesSheet('Actividad', array_merge(
                [['Periodo', "{$mes}/{$año}"]],
                [['Profesor', 'Clases', 'Prom. presentes', 'Último bloque', 'Última fecha']],
                $this->payload['actividad']
            )),
            new ReportesSheet('Alumnos x bloque', array_merge(
                [['Sede', 'Bloque', 'Profesor', 'Alumnos', 'Ingresos aprox.']],
                $this->payload['alumnos_bloque']
            )),
            new ReportesSheet('Financiero sede', array_merge(
                [['Sede', 'Ingresos', 'Gastos', 'Resultado']],
                $this->payload['financiero_sede']
            )),
            new ReportesSheet('Global', array_merge(
                [['Concepto', 'Monto']],
                $this->payload['global']
            )),
        ];
    }
}

class ReportesSheet implements FromArray, WithTitle
{
    public function __construct(
        private string $title,
        private array $rows
    ) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function title(): string
    {
        return mb_substr($this->title, 0, 31);
    }
}
