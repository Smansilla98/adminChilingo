@extends('layouts.app')

@section('title', 'Ver bloque')
@section('page-title', $bloque->nombre)

@section('content')
<div class="card">
    <div class="card-header">Bloque: {{ $bloque->nombre }}</div>
    <div class="card-body">
        <dl class="row">
            <dt class="col-sm-3">Nombre</dt>
            <dd class="col-sm-9">{{ $bloque->nombre }}</dd>

            <dt class="col-sm-3">Año</dt>
            <dd class="col-sm-9">{{ $bloque->año }}</dd>

            <dt class="col-sm-3">A quien corresponde el bloque</dt>
            <dd class="col-sm-9">{{ $bloque->corresponde_a ?? ($bloque->profesor->nombre ?? '-') }}</dd>

            <dt class="col-sm-3">Profesor</dt>
            <dd class="col-sm-9">{{ $bloque->profesor->nombre ?? '-' }}</dd>

            <dt class="col-sm-3">Tambores</dt>
            <dd class="col-sm-9">
                @if($bloque->tambores && count($bloque->tambores) > 0)
                    {{ implode(', ', $bloque->tambores) }}
                @else
                    —
                @endif
            </dd>

            <dt class="col-sm-3">Cantidad máxima de personas</dt>
            <dd class="col-sm-9">{{ $bloque->cantidad_max_alumnos }}</dd>

            <dt class="col-sm-3">Sede</dt>
            <dd class="col-sm-9">{{ $bloque->sede->nombre ?? '-' }}</dd>

            <dt class="col-sm-3">Activo</dt>
            <dd class="col-sm-9">{{ $bloque->activo ? 'Sí' : 'No' }}</dd>
        </dl>
        <a href="{{ route('bloques.edit', $bloque) }}" class="btn btn-warning">Editar</a>
        <a href="{{ route('bloques.index') }}" class="btn btn-secondary">Volver</a>
    </div>
</div>
@endsection
