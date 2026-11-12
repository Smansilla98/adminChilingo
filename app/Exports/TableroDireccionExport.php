<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Excel del tablero de dirección: una hoja por bloque de datos. */
class TableroDireccionExport implements WithMultipleSheets
{
    /** @param  array<string, list<list<mixed>>>  $hojas */
    public function __construct(private array $hojas) {}

    public function sheets(): array
    {
        $out = [];
        foreach ($this->hojas as $titulo => $filas) {
            $out[] = new class($titulo, $filas) implements FromArray, WithTitle
            {
                /** @param  list<list<mixed>>  $filas */
                public function __construct(private string $titulo, private array $filas) {}

                public function array(): array
                {
                    return $this->filas;
                }

                public function title(): string
                {
                    return mb_substr($this->titulo, 0, 31);
                }
            };
        }

        return $out;
    }
}
