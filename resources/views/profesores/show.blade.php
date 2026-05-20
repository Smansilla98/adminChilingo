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
        @if(!empty($alumnoPerfil))
        <div class="alert alert-info py-2 small mb-3">
            <i class="bi bi-mortarboard"></i> También tiene perfil de <strong>alumno</strong>:
            <a href="{{ route('alumnos.show', $alumnoPerfil) }}">{{ $alumnoPerfil->nombre_apellido }}</a>
        </div>
        @endif
        @if($profesor->sedesConRol && $profesor->sedesConRol->isNotEmpty())
        <h6 class="mt-3">Roles por sede</h6>
        <ul class="list-group mb-3">
            @foreach($profesor->sedesConRol as $sede)
            <li class="list-group-item d-flex justify-content-between">
                <span>{{ $sede->nombre }}</span>
                <span class="badge bg-primary">{{ \App\Models\Profesor::ROLES_SEDE[$sede->pivot->rol] ?? $sede->pivot->rol }}</span>
            </li>
            @endforeach
        </ul>
        @endif
        @php
            $rolLabels = [
                'titular' => 'Titular',
                'ayudante' => 'Ayudante',
                'suplente' => 'Suplente',
                'coordinador_clase' => 'Coordinador de clase',
            ];
        @endphp
        @if($profesor->bloques->isNotEmpty())
        <h6 class="mt-3">Bloques y roles</h6>
        <ul class="list-group">
            @foreach($profesor->bloques as $bloque)
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span>{{ $bloque->nombre ?? 'Bloque' }}</span>
                <span>
                    @if($bloque->pivot->rol ?? null)
                    <span class="badge bg-primary me-1">{{ $rolLabels[$bloque->pivot->rol] ?? $bloque->pivot->rol }}</span>
                    @endif
                    @if($bloque->sede ?? null)
                    <span class="badge bg-secondary">{{ $bloque->sede->nombre }}</span>
                    @endif
                </span>
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
