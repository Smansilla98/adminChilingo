@extends('layouts.app')

@section('title', $sede->nombre)
@section('page-title', $sede->nombre)

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Sede: {{ $sede->nombre }}</h5>
        <div>
            <a href="{{ route('sedes.edit', $sede) }}" class="btn btn-warning btn-sm"><i class="bi bi-pencil"></i> Editar</a>
            <a href="{{ route('sedes.index') }}" class="btn btn-secondary btn-sm">Volver</a>
        </div>
    </div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">Nombre</dt>
            <dd class="col-sm-9">{{ $sede->nombre }}</dd>
            <dt class="col-sm-3">Dirección</dt>
            <dd class="col-sm-9">{{ $sede->direccion ?? '—' }}</dd>
            <dt class="col-sm-3">Tipo de propiedad</dt>
            <dd class="col-sm-9">{{ $sede->tipo_propiedad === 'propia' ? 'Propia' : ($sede->tipo_propiedad === 'alquilada' ? 'Alquilada' : ucfirst($sede->tipo_propiedad ?? '—')) }}</dd>
            @if($sede->costo_alquiler_mensual)
            <dt class="col-sm-3">Costo alquiler mensual</dt>
            <dd class="col-sm-9">$ {{ number_format($sede->costo_alquiler_mensual, 2, ',', '.') }}</dd>
            @endif
            <dt class="col-sm-3">Estado</dt>
            <dd class="col-sm-9">{{ $sede->activo ? 'Activa' : 'Inactiva' }}</dd>
        </dl>
        <hr>
        <h6 class="mb-2">Bloques ({{ $sede->bloques->count() }})</h6>
        @if($sede->bloques->count() > 0)
        <ul class="list-group list-group-flush">
            @foreach($sede->bloques as $bloque)
            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                <a href="{{ route('bloques.show', $bloque) }}">{{ $bloque->nombre }}</a>
                @if($bloque->profesor)<span class="badge bg-secondary">{{ $bloque->profesor->nombre }}</span>@endif
            </li>
            @endforeach
        </ul>
        @else
        <p class="text-muted small">Sin bloques en esta sede.</p>
        @endif
        <h6 class="mb-2 mt-3">Eventos ({{ $sede->eventos->count() }})</h6>
        @if($sede->eventos->count() > 0)
        <ul class="list-group list-group-flush">
            @foreach($sede->eventos->take(5) as $evento)
            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                <a href="{{ route('eventos.show', $evento) }}">{{ $evento->titulo }}</a>
                <span class="text-muted small">{{ $evento->fecha->format('d/m/Y') }}</span>
            </li>
            @endforeach
        </ul>
        @if($sede->eventos->count() > 5)
        <p class="small text-muted">y {{ $sede->eventos->count() - 5 }} más.</p>
        @endif
        @else
        <p class="text-muted small">Sin eventos en esta sede.</p>
        @endif
    </div>
</div>
@endsection
