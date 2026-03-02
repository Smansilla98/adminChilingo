@extends('layouts.app')

@section('title', 'Profesor')
@section('page-title', 'Profesor')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">{{ $profesor->nombre }}</h5>
        <div>
            <a href="{{ route('profesores.edit', $profesor) }}" class="btn btn-warning btn-sm"><i class="bi bi-pencil"></i> Editar</a>
            <a href="{{ route('profesores.index') }}" class="btn btn-secondary btn-sm">Volver</a>
        </div>
    </div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-6">
                <strong>Teléfono:</strong> {{ $profesor->telefono ?? '—' }}
            </div>
            <div class="col-md-6">
                <strong>Correo:</strong> {{ $profesor->email ?? '—' }}
            </div>
        </div>
        <div class="mb-3">
            <strong>Estado:</strong> {{ $profesor->activo ? 'Activo' : 'Inactivo' }}
        </div>
        @if($profesor->bloques->isNotEmpty())
        <h6 class="mt-3">Bloques asignados</h6>
        <ul class="list-group">
            @foreach($profesor->bloques as $bloque)
            <li class="list-group-item d-flex justify-content-between align-items-center">
                {{ $bloque->nombre ?? 'Bloque' }}
                @if($bloque->sede ?? null)
                <span class="badge bg-secondary">{{ $bloque->sede->nombre }}</span>
                @endif
            </li>
            @endforeach
        </ul>
        @endif
        @if(isset($profesor->eventos) && $profesor->eventos->isNotEmpty())
        <h6 class="mt-3">Eventos</h6>
        <ul class="list-group">
            @foreach($profesor->eventos->take(10) as $evento)
            <li class="list-group-item">{{ $evento->titulo ?? 'Evento' }} — {{ $evento->fecha ? $evento->fecha->format('d/m/Y') : '' }}</li>
            @endforeach
        </ul>
        @endif
    </div>
</div>
@endsection
