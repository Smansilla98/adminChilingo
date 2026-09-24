@extends('layouts.app')

@section('title', $show->titulo)
@section('page-title', $show->titulo)

@section('content')
@php
    $horario = collect([$show->hora_inicio?->format('H:i'), $show->hora_fin?->format('H:i')])->filter()->join(' a ');
    $esFuturo = $show->fecha && $show->fecha->endOfDay()->isFuture();
@endphp
<x-ito.shell-page :title="$show->titulo" :subtitle="ucfirst($show->fecha->locale('es')->translatedFormat('l j \d\e F Y')).($horario ? ' · '.$horario.' hs' : '')" :plain="true">
    <x-slot:actions>
        <x-ito.status :tone="$esFuturo ? 'info' : 'neutral'" :label="$esFuturo ? 'Próximo' : 'Realizado'" />
        <a href="{{ route('shows.index') }}" class="btn btn-outline-secondary">Volver</a>
        @can('update', $show)
            <a href="{{ route('shows.edit', $show) }}" class="btn btn-primary"><i class="bi bi-pencil" aria-hidden="true"></i> Editar</a>
        @endcan
    </x-slot:actions>

    <x-ito.facts>
        <x-ito.fact label="Lugar" :value="$show->lugar" />
        <x-ito.fact label="Participación" :value="$show->convocatoria_abierta ? 'Convocatoria abierta' : $show->bloques->count().' bloques'" />
    </x-ito.facts>

    <div class="ito-detail-grid">
        <x-ito.detail-section title="Descripción" icon="bi-text-paragraph">
            @if($show->descripcion)
                <p class="mb-0" style="white-space: pre-line">{{ $show->descripcion }}</p>
            @else
                <p class="text-muted mb-0">Sin descripción.</p>
            @endif
        </x-ito.detail-section>
        <x-ito.detail-section title="Bloques" icon="bi-collection">
            @if($show->convocatoria_abierta)
                <p class="mb-0">Convocatoria abierta: puede sumarse cualquier alumno.</p>
            @else
                @forelse($show->bloques as $b)
                    <a class="hub-list-item hub-list-item--link" href="{{ route('bloques.show', $b) }}">
                        <span class="fw-semibold">{{ $b->nombre }}</span>
                        <span class="small text-muted ms-auto">{{ $b->sede->nombre ?? '' }}</span>
                    </a>
                @empty
                    <p class="text-muted mb-0">Sin bloques asignados.</p>
                @endforelse
            @endif
        </x-ito.detail-section>
    </div>
</x-ito.shell-page>
@endsection
