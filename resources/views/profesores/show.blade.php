@extends('layouts.app')

@section('title', $profesor->nombre)
@section('page-title', $profesor->nombre)

@section('content')
@php
    $rolLabels = ['titular' => 'Titular', 'ayudante' => 'Ayudante', 'suplente' => 'Suplente', 'coordinador_clase' => 'Coordinador de clase'];
    $sedesRol = $profesor->sedesConRol ?? collect();
    $eventos = isset($profesor->eventos) ? $profesor->eventos->sortByDesc('fecha')->take(8) : collect();
@endphp
<x-ito.shell-page :title="$profesor->nombre" :subtitle="$profesor->bloques->pluck('sede.nombre')->filter()->unique()->join(' · ') ?: 'Sin bloques asignados'" :plain="true">
    <x-slot:actions>
        <x-ito.status :tone="$profesor->activo ? 'success' : 'neutral'" :label="$profesor->activo ? 'Activo' : 'Inactivo'" />
        <a href="{{ route('profesores.index') }}" class="btn btn-outline-secondary">Volver</a>
        @if($profesor->persona_id)
            <a href="{{ route('personas.show', $profesor->persona_id) }}" class="btn btn-outline-secondary"><i class="bi bi-person-vcard" aria-hidden="true"></i> Ficha</a>
        @endif
        <a href="{{ route('profesores.edit', $profesor) }}" class="btn btn-primary"><i class="bi bi-pencil" aria-hidden="true"></i> Editar</a>
    </x-slot:actions>

    <x-ito.facts>
        <x-ito.fact label="Bloques" :value="$profesor->bloques->count()" />
        <x-ito.fact label="Sedes" :value="$profesor->bloques->pluck('sede_id')->filter()->unique()->count()" />
        <x-ito.fact label="Teléfono" :value="$profesor->telefono" />
        <x-ito.fact label="Cuenta" :value="$profesor->user ? ($profesor->user->username ?: $profesor->user->email) : 'Sin cuenta'" />
    </x-ito.facts>

    @if(!empty($alumnoPerfil))
        <div class="alert alert-info mb-0">
            <i class="bi bi-mortarboard" aria-hidden="true"></i> También es <strong>alumno</strong>:
            <a href="{{ route('alumnos.show', $alumnoPerfil) }}">{{ $alumnoPerfil->nombre_apellido }}</a>
        </div>
    @endif

    <div class="ito-detail-grid">
        <div class="ito-detail-col">
            <x-ito.detail-section title="Bloques y rol" icon="bi-collection" :flush="true">
                @if($profesor->bloques->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table align-middle mb-0" data-ito-no-cards>
                            <thead><tr><th>Bloque</th><th>Sede</th><th class="text-end">Rol</th></tr></thead>
                            <tbody>
                                @foreach($profesor->bloques as $bloque)
                                    <tr>
                                        <td><a href="{{ route('bloques.show', $bloque) }}">{{ $bloque->nombre ?? 'Bloque' }}</a></td>
                                        <td class="text-muted">{{ $bloque->sede?->nombre ?? '—' }}</td>
                                        <td class="text-end"><x-ito.status tone="info" :label="$rolLabels[$bloque->pivot->rol ?? ''] ?? ucfirst($bloque->pivot->rol ?? 'Docente')" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <x-ito.empty icon="bi-collection" title="Sin bloques" description="Asignale bloques desde Editar." />
                @endif
            </x-ito.detail-section>

            @if($sedesRol->isNotEmpty())
                <x-ito.detail-section title="Roles por sede" icon="bi-geo-alt">
                    @foreach($sedesRol as $sede)
                        <div class="hub-list-item">
                            <span class="fw-semibold">{{ $sede->nombre }}</span>
                            <span class="ms-auto"><x-ito.status tone="info" :label="\App\Models\Profesor::ROLES_SEDE[$sede->pivot->rol] ?? $sede->pivot->rol" /></span>
                        </div>
                    @endforeach
                </x-ito.detail-section>
            @endif

            <x-ito.detail-section title="Eventos" icon="bi-calendar-event">
                @forelse($eventos as $evento)
                    <a class="hub-list-item hub-list-item--link" href="{{ route('eventos.show', $evento) }}">
                        <span class="agenda-date" aria-hidden="true">
                            <span class="agenda-date-d">{{ $evento->fecha?->format('d') }}</span>
                            <span class="agenda-date-m">{{ $evento->fecha?->locale('es')->translatedFormat('M') }}</span>
                        </span>
                        <span class="fw-semibold">{{ $evento->titulo ?? 'Evento' }}</span>
                    </a>
                @empty
                    <p class="text-muted mb-0">No tiene eventos a cargo.</p>
                @endforelse
            </x-ito.detail-section>
        </div>

        <div class="ito-detail-col">
            <x-ito.detail-section title="Contacto" icon="bi-telephone">
                <x-ito.contact-actions
                    :telefono="$profesor->telefono"
                    :email="$profesor->email"
                    :nombre="$profesor->nombre"
                    mensaje="Hola {{ $profesor->nombre }}, te escribimos de La Chilinga."
                />
            </x-ito.detail-section>
            <x-ito.detail-section title="Acceso al sistema" icon="bi-key">
                @if($profesor->user)
                    <p class="mb-1">Usuario <span class="ito-mono">{{ $profesor->user->username ?: $profesor->user->email }}</span></p>
                    <p class="small text-muted mb-0">La contraseña se cambia desde Editar.</p>
                @else
                    <p class="mb-2">Todavía no tiene cuenta para entrar.</p>
                    <a href="{{ route('profesores.edit', $profesor) }}" class="btn btn-sm btn-outline-primary">Crear usuario</a>
                @endif
            </x-ito.detail-section>
        </div>
    </div>
</x-ito.shell-page>
@endsection
