@extends('layouts.app')

@section('title', $evento->titulo)
@section('page-title', $evento->titulo)

@section('content')
<x-ito.shell-page
    title="{{ $evento->titulo }}"
    subtitle="Detalle del evento"
    eyebrow="Eventos"
>
    <x-slot:actions>
        <a href="{{ route('eventos.edit', $evento) }}" class="btn btn-warning btn-sm"><i class="bi bi-pencil"></i> Editar</a>
        <a href="{{ route('eventos.index') }}" class="btn btn-outline-secondary btn-sm">Volver</a>
    </x-slot:actions>

    <dl class="ito-dl">
        <div><dt>Tipo</dt><dd><span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $evento->tipo_evento)) }}</span></dd></div>
        <div><dt>Fecha</dt><dd>{{ $evento->fecha->format('d/m/Y') }}</dd></div>
        @if($evento->hora_inicio || $evento->hora_fin)
        <div>
            <dt>Horario</dt>
            <dd>
                @if($evento->hora_inicio) {{ $evento->hora_inicio->format('H:i') }} @endif
                @if($evento->hora_fin) — {{ $evento->hora_fin->format('H:i') }} @endif
            </dd>
        </div>
        @endif
        <div><dt>Sede</dt><dd>{{ $evento->sede?->nombre ?? '—' }}</dd></div>
        <div><dt>Profesor</dt><dd>{{ $evento->profesor?->nombre ?? '—' }}</dd></div>
        <div><dt>Bloque</dt><dd>{{ $evento->bloque?->nombre ?? '—' }}</dd></div>
        @if($evento->cantidad_personas !== null)
        <div><dt>Cant. personas</dt><dd>{{ $evento->cantidad_personas }}</dd></div>
        @endif
        @if($evento->descripcion)
        <div><dt>Descripción</dt><dd>{{ $evento->descripcion }}</dd></div>
        @endif
        @if($evento->creador)
        <div><dt>Creado por</dt><dd>{{ $evento->creador->name ?? $evento->creador->username }}</dd></div>
        @endif
    </dl>
</x-ito.shell-page>
@endsection
