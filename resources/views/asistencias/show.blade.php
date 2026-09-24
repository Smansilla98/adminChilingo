@extends('layouts.app')

@section('title', 'Asistencia')
@section('page-title', 'Detalle de asistencia')

@section('content')
@php
    $tipo = $asistencia->tipo_asistencia;
    $tono = match (true) {
        $tipo === 'presente' => 'success',
        $tipo === 'tarde' => 'warning',
        in_array($tipo, ['ausencia_justificada', 'justificado'], true) => 'info',
        in_array($tipo, ['ausencia_injustificada', 'ausente'], true) => 'danger',
        default => 'neutral',
    };
@endphp
<x-ito.shell-page :title="$asistencia->alumno->nombre_apellido ?? 'Asistencia'" :subtitle="ucfirst($asistencia->fecha->locale('es')->translatedFormat('l j \d\e F Y'))" :plain="true">
    <x-slot:actions>
        <x-ito.status :tone="$tono" :label="\App\Models\Asistencia::TIPOS_ASISTENCIA[$tipo] ?? $tipo" />
        <a href="{{ route('asistencias.index', ['bloque_id' => $asistencia->bloque_id]) }}" class="btn btn-outline-secondary">Volver a la planilla</a>
        <a href="{{ route('asistencias.edit', $asistencia) }}" class="btn btn-primary"><i class="bi bi-pencil" aria-hidden="true"></i> Corregir</a>
    </x-slot:actions>

    <x-ito.facts>
        <x-ito.fact label="Fecha" :value="$asistencia->fecha->format('d/m/Y')" />
        <x-ito.fact label="Bloque" :value="$asistencia->bloque->nombre ?? null" />
        <x-ito.fact label="Alumno">
            @if($asistencia->alumno)
                <a href="{{ route('alumnos.show', $asistencia->alumno) }}">{{ $asistencia->alumno->nombre_apellido }}</a>
            @else
                —
            @endif
        </x-ito.fact>
    </x-ito.facts>
</x-ito.shell-page>
@endsection
