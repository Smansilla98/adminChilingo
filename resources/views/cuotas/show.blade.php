@extends('layouts.app')

@section('title', $cuota->nombre)
@section('page-title', $cuota->nombre)

@section('content')
<div class="card">
    <div class="card-header">Cuota</div>
    <div class="card-body">
        <dl class="row">
            <dt class="col-sm-3">Nombre</dt>
            <dd class="col-sm-9">{{ $cuota->nombre }}</dd>
            <dt class="col-sm-3">Año / Mes</dt>
            <dd class="col-sm-9">{{ $cuota->año }} {{ $cuota->nombre_mes ? '- ' . $cuota->nombre_mes : '' }}</dd>
            <dt class="col-sm-3">Monto</dt>
            <dd class="col-sm-9">$ {{ number_format($cuota->monto, 2, ',', '.') }}</dd>
            <dt class="col-sm-3">Registros de pago</dt>
            <dd class="col-sm-9">{{ $cuota->pago_detalles_count }}</dd>
        </dl>
        <a href="{{ route('cuotas.edit', $cuota) }}" class="btn btn-warning">Editar</a>
        <a href="{{ route('cuotas.index') }}" class="btn btn-secondary">Volver</a>
    </div>
</div>
@endsection
