@extends('layouts.app')

@section('title', 'Editar Alumno')
@section('page-title', 'Editar Alumno')

@section('content')
<x-ito.shell-page
    title="Editar alumno"
    subtitle="{{ $alumno->nombre_apellido }}"
    eyebrow="Alumnos"
>
    <x-slot:actions>
        <a href="{{ route('alumnos.show', $alumno) }}" class="btn btn-outline-secondary btn-sm">Ver ficha</a>
    </x-slot:actions>

    <form action="{{ route('alumnos.update', $alumno) }}" method="POST" class="ito-form">
        @csrf
        @method('PUT')
        <x-ito.form-steps
            :steps="['Datos', 'Instrumentos', 'Clases']"
            submit-label="Actualizar"
        >
            <x-slot:cancel>
                <a href="{{ route('alumnos.show', $alumno) }}" class="btn btn-outline-secondary">Cancelar</a>
            </x-slot:cancel>
            @include('alumnos._form', [
                'alumno' => $alumno,
                'instrumentos' => $instrumentos,
                'tiposTambor' => $tiposTambor,
                'procedenciasTambor' => $procedenciasTambor,
                'sedes' => $sedes,
                'bloques' => $bloques,
                'profesoresSinVinculo' => $profesoresSinVinculo ?? collect(),
            ])
        </x-ito.form-steps>
    </form>
</x-ito.shell-page>
@endsection
