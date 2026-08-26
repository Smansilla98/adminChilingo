@extends('layouts.app')

@section('title', 'Profesor')
@section('page-title', 'Profesor')

@section('content')
<x-ito.shell-page
    title="{{ $profesor->nombre }}"
    subtitle="Ficha del docente"
    eyebrow="Profesores"
>
    <x-slot:actions>
        <a href="{{ route('profesores.edit', $profesor) }}" class="btn btn-warning btn-sm"><i class="bi bi-pencil"></i> Editar</a>
        <a href="{{ route('profesores.index') }}" class="btn btn-outline-secondary btn-sm">Volver</a>
    </x-slot:actions>

    <div class="ito-show-grid">
        <section class="ito-show-block">
            <h2 class="ito-show-title">Contacto</h2>
            <x-ito.contact-actions
                :telefono="$profesor->telefono"
                :email="$profesor->email"
                :nombre="$profesor->nombre"
                mensaje="Hola {{ $profesor->nombre }}, te escribimos de La Chilinga."
            />
            <dl class="ito-dl mt-3">
                <div><dt>Estado</dt><dd>{{ $profesor->activo ? 'Activo' : 'Inactivo' }}</dd></div>
                <div>
                    <dt>Ingreso</dt>
                    <dd>
                        @if($profesor->user)
                            {{ $profesor->user->username ?: $profesor->user->email }}
                            <span class="text-muted small">— contraseña desde Editar</span>
                        @else
                            Sin cuenta.
                            <a href="{{ route('profesores.edit', $profesor) }}">Crear usuario</a>
                        @endif
                    </dd>
                </div>
            </dl>
        </section>
    </div>

    @if(!empty($alumnoPerfil))
    <div class="alert alert-info py-2 small mt-3 mb-0">
        <i class="bi bi-mortarboard"></i> También tiene perfil de <strong>alumno</strong>:
        <a href="{{ route('alumnos.show', $alumnoPerfil) }}">{{ $alumnoPerfil->nombre_apellido }}</a>
    </div>
    @endif

    @if($profesor->sedesConRol && $profesor->sedesConRol->isNotEmpty())
    <section class="ito-show-block mt-4">
        <h2 class="ito-show-title">Roles por sede</h2>
        <ul class="list-group list-group-flush ito-list-flush">
            @foreach($profesor->sedesConRol as $sede)
            <li class="list-group-item d-flex justify-content-between">
                <span>{{ $sede->nombre }}</span>
                <span class="badge bg-primary">{{ \App\Models\Profesor::ROLES_SEDE[$sede->pivot->rol] ?? $sede->pivot->rol }}</span>
            </li>
            @endforeach
        </ul>
    </section>
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
    <section class="ito-show-block mt-4">
        <h2 class="ito-show-title">Bloques y roles</h2>
        <ul class="list-group list-group-flush ito-list-flush">
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
    </section>
    @endif

    @if(isset($profesor->eventos) && $profesor->eventos->isNotEmpty())
    <section class="ito-show-block mt-4">
        <h2 class="ito-show-title">Eventos</h2>
        <ul class="list-group list-group-flush ito-list-flush">
            @foreach($profesor->eventos->take(10) as $evento)
            <li class="list-group-item">{{ $evento->titulo ?? 'Evento' }} — {{ $evento->fecha ? $evento->fecha->format('d/m/Y') : '' }}</li>
            @endforeach
        </ul>
    </section>
    @endif
</x-ito.shell-page>
@endsection
