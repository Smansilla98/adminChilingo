@extends('layouts.app')

@section('title', 'Editar Alumno')
@section('page-title', 'Editar Alumno')

@section('content')
<x-ito.shell-page
    title="Editar alumno"
    subtitle="{{ $alumno->nombre_apellido }}"
    eyebrow="Alumnos"
    :plain="true"
>

    <form action="{{ route('alumnos.update', $alumno) }}" method="POST" >
        @csrf
        @method('PUT')
        @include('alumnos._form', [
                'alumno' => $alumno,
                'instrumentos' => $instrumentos,
                'tiposTambor' => $tiposTambor,
                'procedenciasTambor' => $procedenciasTambor,
                'sedes' => $sedes,
                'bloques' => $bloques,
                'profesoresSinVinculo' => $profesoresSinVinculo ?? collect(),
            ])
        <x-ito.form-actions :cancel="route('alumnos.show', $alumno)" submit="Guardar cambios" />
    </form>
</x-ito.shell-page>
@endsection
