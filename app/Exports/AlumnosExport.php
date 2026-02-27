<?php

namespace App\Exports;

use App\Models\Alumno;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AlumnosExport implements FromCollection, WithHeadings, WithMapping
{
    protected $request;

    public function __construct($request = null)
    {
        $this->request = $request;
    }

    public function collection()
    {
        $query = Alumno::with(['bloque', 'bloques', 'sede']);

        if ($this->request) {
            if ($this->request->filled('sede_id')) {
                $query->where('sede_id', $this->request->sede_id);
            }
            if ($this->request->filled('bloque_id')) {
                $query->whereHas('bloques', function ($q) {
                    $q->where('bloques.id', $this->request->bloque_id);
                });
            }
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nombre y Apellido',
            'DNI',
            'Fecha de Nacimiento',
            'Edad',
            'Teléfono',
            'Instrumento Principal',
            'Instrumento Secundario',
            'Tipo Tambor',
            'Bloque',
            'Sede',
            'Activo',
        ];
    }

    public function map($alumno): array
    {
        return [
            $alumno->id,
            $alumno->nombre_apellido,
            $alumno->dni,
            $alumno->fecha_nacimiento->format('d/m/Y'),
            $alumno->edad,
            $alumno->telefono ?? '',
            $alumno->instrumento_principal,
            $alumno->instrumento_secundario ?? '',
            $alumno->tipo_tambor,
            $alumno->bloques->isNotEmpty() ? $alumno->bloques->pluck('nombre')->unique()->join(', ') : ($alumno->bloque ? $alumno->bloque->nombre : ''),
            $alumno->sede->nombre,
            $alumno->activo ? 'Sí' : 'No',
        ];
    }
}
