@extends('layouts.app')

@section('title', 'Editar asistencia')
@section('page-title', 'Editar asistencia')

@section('content')
@php
    $valorActual = $asistencia->tipo_asistencia;
    if ($valorActual === 'ausente') { $valorActual = 'ausencia_injustificada'; }
    if ($valorActual === 'justificado') { $valorActual = 'ausencia_justificada'; }
    $seleccion = old('tipo_asistencia', $valorActual);
@endphp
<x-ito.shell-page :title="$asistencia->alumno->nombre_apellido ?? 'Asistencia'" :subtitle="ucfirst($asistencia->fecha->locale('es')->translatedFormat('l j \d\e F')).' · '.($asistencia->bloque->nombre ?? '—')" :plain="true">
    <form action="{{ route('asistencias.update', $asistencia) }}" method="POST">
        @csrf
        @method('PUT')
        <x-ito.form-section title="¿Cómo fue la asistencia?" icon="bi-check2-square">
            <fieldset class="asist-chips" role="radiogroup" aria-label="Tipo de asistencia">
                @foreach($tiposAsistencia as $valor => $etiqueta)
                    <label class="asist-chip asist-chip--{{ $valor }}">
                        <input type="radio" name="tipo_asistencia" value="{{ $valor }}" @checked($seleccion === $valor)>
                        <span class="asist-chip-letra" aria-hidden="true">{{ \App\Models\Asistencia::letraTipo($valor) }}</span>
                        <span class="asist-chip-texto">{{ $etiqueta }}</span>
                    </label>
                @endforeach
            </fieldset>
            @error('tipo_asistencia')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </x-ito.form-section>
        <x-ito.form-actions :cancel="route('asistencias.show', $asistencia)" submit="Guardar" />
    </form>
</x-ito.shell-page>
@endsection
