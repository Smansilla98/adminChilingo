@extends('layouts.app')

@section('title', $show->titulo)
@section('page-title', $show->titulo)

@section('content')
<x-ito.shell-page
    title="{{ $show->titulo }}"
    eyebrow="Shows"
    subtitle="{{ $show->fecha->format('d/m/Y') }}"
>

        <dl class="row mb-0">
            <dt class="col-sm-3">Lugar</dt>
            <dd class="col-sm-9">{{ $show->lugar ?? '—' }}</dd>
            <dt class="col-sm-3">Participación</dt>
            <dd class="col-sm-9">
                @if($show->convocatoria_abierta)
                    <span class="badge bg-info">Convocatoria abierta</span>
                @else
                    @foreach($show->bloques as $b)
                        <span class="badge bg-secondary me-1">{{ $b->nombre }} ({{ $b->sede->nombre ?? '' }})</span>
                    @endforeach
                    @if($show->bloques->isEmpty()) — @endif
                @endif
            </dd>
            @if($show->descripcion)
            <dt class="col-sm-3">Descripción</dt>
            <dd class="col-sm-9">{{ $show->descripcion }}</dd>
            @endif
        </dl>
        <a href="{{ route('shows.edit', $show) }}" class="btn btn-primary btn-sm">Editar</a>
        <a href="{{ route('shows.index') }}" class="btn btn-secondary btn-sm">Volver</a>
</x-ito.shell-page>

@endsection
