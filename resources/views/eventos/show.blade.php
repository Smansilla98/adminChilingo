@extends('layouts.app')

@section('title', $evento->titulo)
@section('page-title', $evento->titulo)

@section('content')
@php
    $horario = collect([$evento->hora_inicio?->format('H:i'), $evento->hora_fin?->format('H:i')])->filter()->join(' a ');
    $esFuturo = $evento->fecha && $evento->fecha->endOfDay()->isFuture();
@endphp
<x-ito.shell-page :title="$evento->titulo" :subtitle="ucfirst($evento->fecha->locale('es')->translatedFormat('l j \d\e F Y')).($horario ? ' · '.$horario.' hs' : '')" :plain="true">
    <x-slot:actions>
        <x-ito.status :tone="$esFuturo ? 'info' : 'neutral'" :label="$esFuturo ? 'Próximo' : 'Realizado'" />
        <a href="{{ route('eventos.index') }}" class="btn btn-outline-secondary">Volver</a>
        @can('update', $evento)
            <a href="{{ route('eventos.edit', $evento) }}" class="btn btn-primary"><i class="bi bi-pencil" aria-hidden="true"></i> Editar</a>
        @endcan
    </x-slot:actions>

    <x-ito.facts>
        <x-ito.fact label="Tipo" :value="ucfirst(str_replace('_', ' ', $evento->tipo_evento))" />
        <x-ito.fact label="Sede" :value="$evento->sede?->nombre ?? 'Todas'" />
        <x-ito.fact label="Profesor a cargo" :value="$evento->profesor?->nombre" />
        <x-ito.fact label="Bloque" :value="$evento->bloque?->nombre" />
        <x-ito.fact label="Personas" :value="$evento->cantidad_personas" />
    </x-ito.facts>

    <x-ito.detail-section title="Descripción" icon="bi-text-paragraph">
        @if($evento->descripcion)
            <p class="mb-0" style="white-space: pre-line">{{ $evento->descripcion }}</p>
        @else
            <p class="text-muted mb-0">Sin descripción.</p>
        @endif
        @if($evento->creador)
            <p class="small text-muted mt-3 mb-0">Cargado por {{ $evento->creador->name ?? $evento->creador->username }}.</p>
        @endif
    </x-ito.detail-section>
</x-ito.shell-page>
@endsection
