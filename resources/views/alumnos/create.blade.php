@extends('layouts.app')

@section('title', 'Nuevo Alumno')
@section('page-title', 'Nuevo Alumno')

@section('content')
<x-ito.shell-page
    title="Nuevo alumno"
    subtitle="Alta en tres pasos: datos, instrumentos y clases."
    eyebrow="Alumnos"
>
    <x-slot:actions>
        <a href="{{ route('alumnos.index') }}" class="btn btn-outline-secondary btn-sm">Volver al listado</a>
    </x-slot:actions>

    <form action="{{ route('alumnos.store') }}" method="POST" class="ito-form">
        @csrf
        <x-ito.form-steps
            :steps="['Datos', 'Instrumentos', 'Clases']"
            submit-label="Guardar alumno"
        >
            <x-slot:cancel>
                <a href="{{ route('alumnos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </x-slot:cancel>
            @include('alumnos._form', [
                'alumno' => null,
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
