<?php

namespace App\Exports;

use App\Models\Alumno;
use App\Models\User;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AlumnosExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        protected $request = null,
        protected ?User $user = null,
    ) {}

    public function collection()
    {
        $query = Alumno::with(['bloque', 'bloques', 'sede']);

        if ($this->user && $this->user->acotaPorSede()) {
            $ids = $this->user->sedeIdsOperativas() ?: [0];
            $query->where(function ($q) use ($ids) {
                $q->whereIn('sede_id', $ids)
                    ->orWhereHas('bloques', fn ($b) => $b->whereIn('bloques.sede_id', $ids))
                    ->orWhereHas('bloque', fn ($b) => $b->whereIn('sede_id', $ids));
            });
        }

        if ($this->request instanceof Request) {
            if ($this->request->filled('sede_id')) {
                $query->where('sede_id', $this->request->sede_id);
            }
            if ($this->request->filled('bloque_id')) {
                $query->whereHas('bloques', function ($q) {
                    $q->where('bloques.id', $this->request->bloque_id);
                });
            }
        }

        return $query->orderBy('nombre_apellido')->get();
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
            'Tipo Tambor (Instrumento)',
            'Procedencia Tambor',
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
            $alumno->fecha_nacimiento ? $alumno->fecha_nacimiento->format('d/m/Y') : '',
            $alumno->edad ?? '',
            $alumno->telefono ?? '',
            $alumno->instrumento_principal,
            $alumno->instrumento_secundario ?? '',
            $alumno->tipo_tambor ?? '',
            $alumno->tambor_procedencia ?? '',
            $alumno->bloques->isNotEmpty() ? $alumno->bloques->pluck('nombre')->unique()->join(', ') : ($alumno->bloque ? $alumno->bloque->nombre : ''),
            $alumno->sede->nombre ?? '',
            $alumno->activo ? 'Sí' : 'No',
        ];
    }
}
