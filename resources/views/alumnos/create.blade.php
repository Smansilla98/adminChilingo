@extends('layouts.app')

@section('title', 'Nuevo Alumno')
@section('page-title', 'Nuevo Alumno')

@section('content')
<x-ito.shell-page
    title="Nuevo alumno"
    subtitle="Datos personales, instrumento y clases."
    eyebrow="Alumnos"
    :plain="true"
>

    <form action="{{ route('alumnos.store') }}" method="POST" >
        @csrf
        @isset($persona)
            @if($persona)
                <input type="hidden" name="persona_id" value="{{ $persona->id }}">
                <div class="alert alert-info">Se va a crear para <strong>{{ $persona->nombre_completo }}</strong> (ficha existente): no se duplica la persona.@if($persona->user ?? null) Usará su cuenta <span class="ito-mono">{{ $persona->user->username }}</span>.@endif</div>
            @endif
        @endisset
        @php($alumno = ($persona ?? null) ? new \App\Models\Alumno(['nombre_apellido' => $persona->nombre_completo, 'dni' => $persona->dni, 'fecha_nacimiento' => $persona->fecha_nacimiento, 'telefono' => $persona->telefono]) : ($alumno ?? null))
        @include('alumnos._form', [
                'alumno' => $alumno,
                'instrumentos' => $instrumentos,
                'tiposTambor' => $tiposTambor,
                'procedenciasTambor' => $procedenciasTambor,
                'sedes' => $sedes,
                'bloques' => $bloques,
                'profesoresSinVinculo' => $profesoresSinVinculo ?? collect(),
            ])
        <x-ito.form-actions :cancel="route('alumnos.index')" submit="Crear alumno" />
    </form>
</x-ito.shell-page>
@endsection
