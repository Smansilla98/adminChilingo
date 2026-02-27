@extends('layouts.app')

@section('title', $evento->titulo)
@section('page-title', $evento->titulo)

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">{{ $evento->titulo }}</h5>
        <div>
            <a href="{{ route('eventos.edit', $evento) }}" class="btn btn-warning btn-sm"><i class="bi bi-pencil"></i> Editar</a>
            <a href="{{ route('eventos.index') }}" class="btn btn-secondary btn-sm">Volver</a>
        </div>
    </div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">Tipo</dt>
            <dd class="col-sm-9"><span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $evento->tipo_evento)) }}</span></dd>

            <dt class="col-sm-3">Fecha</dt>
            <dd class="col-sm-9">{{ $evento->fecha->format('d/m/Y') }}</dd>

            @if($evento->hora_inicio || $evento->hora_fin)
            <dt class="col-sm-3">Horario</dt>
            <dd class="col-sm-9">
                @if($evento->hora_inicio) {{ $evento->hora_inicio->format('H:i') }} @endif
                @if($evento->hora_fin) — {{ $evento->hora_fin->format('H:i') }} @endif
            </dd>
            @endif

            <dt class="col-sm-3">Sede</dt>
            <dd class="col-sm-9">{{ $evento->sede?->nombre ?? '—' }}</dd>

            <dt class="col-sm-3">Profesor</dt>
            <dd class="col-sm-9">{{ $evento->profesor?->nombre ?? '—' }}</dd>

            <dt class="col-sm-3">Bloque</dt>
            <dd class="col-sm-9">{{ $evento->bloque?->nombre ?? '—' }}</dd>

            @if($evento->cantidad_personas !== null)
            <dt class="col-sm-3">Cant. personas</dt>
            <dd class="col-sm-9">{{ $evento->cantidad_personas }}</dd>
            @endif

            @if($evento->descripcion)
            <dt class="col-sm-3">Descripción</dt>
            <dd class="col-sm-9">{{ $evento->descripcion }}</dd>
            @endif

            @if($evento->creador)
            <dt class="col-sm-3">Creado por</dt>
            <dd class="col-sm-9">{{ $evento->creador->name ?? $evento->creador->username }}</dd>
            @endif
        </dl>
    </div>
</div>
@endsection
