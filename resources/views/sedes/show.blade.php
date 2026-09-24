@extends('layouts.app')

@section('title', $sede->nombre)
@section('page-title', $sede->nombre)

@section('content')
@php
    $tipos = ['propia' => 'Propia', 'alquilada' => 'Alquilada', 'compartida' => 'Compartida', 'otro' => 'Otro'];
    $conReparto = \Illuminate\Support\Facades\Schema::hasColumn('sedes', 'liquidacion_porc_docente');
@endphp
<x-ito.shell-page :title="$sede->nombre" :subtitle="$sede->direccion ?: 'Sin dirección cargada'" :plain="true">
    <x-slot:actions>
        <x-ito.status :tone="$sede->activo ? 'success' : 'neutral'" :label="$sede->activo ? 'Activa' : 'Inactiva'" />
        <a href="{{ route('sedes.index') }}" class="btn btn-outline-secondary">Volver</a>
        @can('update', $sede)
            <a href="{{ route('sedes.edit', $sede) }}" class="btn btn-primary"><i class="bi bi-pencil" aria-hidden="true"></i> Editar</a>
        @endcan
    </x-slot:actions>

    <x-ito.facts>
        <x-ito.fact label="Bloques" :value="$sede->bloques->count()" />
        <x-ito.fact label="Propiedad" :value="$tipos[$sede->tipo_propiedad] ?? ucfirst($sede->tipo_propiedad ?? '—')" />
        <x-ito.fact label="Alquiler mensual" :value="$sede->costo_alquiler_mensual ? '$ '.number_format($sede->costo_alquiler_mensual, 0, ',', '.') : null" />
        @if($conReparto)
            <x-ito.fact label="Se queda la escuela" :value="'$ '.number_format((float) ($sede->liquidacion_retencion_escuela ?? 0), 0, ',', '.')" />
            <x-ito.fact label="Para el profesor" :value="number_format((float) ($sede->liquidacion_porc_docente ?? 40), 1, ',', '.').' %'" />
        @endif
    </x-ito.facts>

    <div class="ito-detail-grid">
        <x-ito.detail-section title="Bloques" icon="bi-collection" :flush="true">
            @if($sede->bloques->isNotEmpty())
                <div class="table-responsive">
                    <table class="table align-middle mb-0" data-ito-no-cards>
                        <thead><tr><th>Bloque</th><th>Profesor</th><th class="text-end">Estado</th></tr></thead>
                        <tbody>
                            @foreach($sede->bloques as $bloque)
                                <tr>
                                    <td><a href="{{ route('bloques.show', $bloque) }}">{{ $bloque->nombre }}</a></td>
                                    <td class="text-muted">{{ $bloque->profesor?->nombre ?? '—' }}</td>
                                    <td class="text-end"><x-ito.status :tone="$bloque->activo ? 'success' : 'neutral'" :label="$bloque->activo ? 'Activo' : 'Inactivo'" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <x-ito.empty icon="bi-collection" title="Sin bloques" description="Todavía no hay bloques en esta sede."
                    :action-href="auth()->user()->can('bloques.create') ? route('bloques.create') : null" action-label="Nuevo bloque" />
            @endif
        </x-ito.detail-section>

        <x-ito.detail-section title="Eventos" icon="bi-calendar-event">
            @forelse($sede->eventos->sortByDesc('fecha')->take(6) as $evento)
                <a class="hub-list-item hub-list-item--link" href="{{ route('eventos.show', $evento) }}">
                    <span class="agenda-date" aria-hidden="true">
                        <span class="agenda-date-d">{{ $evento->fecha->format('d') }}</span>
                        <span class="agenda-date-m">{{ $evento->fecha->locale('es')->translatedFormat('M') }}</span>
                    </span>
                    <span class="min-w-0">
                        <span class="d-block fw-semibold text-truncate">{{ $evento->titulo }}</span>
                        <span class="d-block small text-muted">{{ $evento->fecha->format('d/m/Y') }}</span>
                    </span>
                </a>
            @empty
                <x-ito.empty icon="bi-calendar-event" title="Sin eventos" description="No hay eventos cargados para esta sede." />
            @endforelse
            @if($sede->eventos->count() > 6)
                <p class="small text-muted mt-2 mb-0">Y {{ $sede->eventos->count() - 6 }} más en <a href="{{ route('eventos.index', ['sede_id' => $sede->id]) }}">Eventos</a>.</p>
            @endif
        </x-ito.detail-section>
    </div>
</x-ito.shell-page>
@endsection
