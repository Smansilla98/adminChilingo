@extends('layouts.app')

@section('title', 'Ver gasto')
@section('page-title', 'Ver gasto')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Gasto #{{ $gasto->id }}</h5>
        <div>
            <a href="{{ route('gastos.edit', $gasto) }}" class="btn btn-warning btn-sm"><i class="bi bi-pencil"></i> Editar</a>
            <a href="{{ route('gastos.index') }}" class="btn btn-secondary btn-sm">Volver</a>
        </div>
    </div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">Fecha</dt>
            <dd class="col-sm-9">{{ $gasto->fecha->format('d/m/Y') }}</dd>

            <dt class="col-sm-3">Tipo</dt>
            <dd class="col-sm-9">{{ \App\Models\Gasto::TIPOS[$gasto->tipo] ?? $gasto->tipo }}</dd>

            @if($gasto->subtipo)
            <dt class="col-sm-3">Subtipo</dt>
            <dd class="col-sm-9">{{ $gasto->subtipo }}</dd>
            @endif

            <dt class="col-sm-3">Monto</dt>
            <dd class="col-sm-9"><strong>$ {{ number_format($gasto->monto, 2, ',', '.') }}</strong></dd>

            <dt class="col-sm-3">Sede</dt>
            <dd class="col-sm-9">{{ $gasto->sede?->nombre ?? '—' }}</dd>

            <dt class="col-sm-3">Bloque</dt>
            <dd class="col-sm-9">{{ $gasto->bloque?->nombre ?? '—' }}</dd>

            @if($gasto->descripcion)
            <dt class="col-sm-3">Descripción</dt>
            <dd class="col-sm-9">{{ $gasto->descripcion }}</dd>
            @endif

            @if($gasto->proveedor)
            <dt class="col-sm-3">Proveedor</dt>
            <dd class="col-sm-9">{{ $gasto->proveedor }}</dd>
            @endif

            @if($gasto->notas)
            <dt class="col-sm-3">Notas</dt>
            <dd class="col-sm-9">{{ $gasto->notas }}</dd>
            @endif

            @if($gasto->creador)
            <dt class="col-sm-3">Registrado por</dt>
            <dd class="col-sm-9">{{ $gasto->creador->name ?? $gasto->creador->username }}</dd>
            @endif
        </dl>
    </div>
</div>
@endsection
