@extends('layouts.app')

@section('title', 'Editar facturación')
@section('page-title', 'Editar facturación — ' . $facturacionMensual->nombre_mes . ' ' . $facturacionMensual->año)

@section('content')
<div class="card">
    <div class="card-header">Editar facturación mensual</div>
    <div class="card-body">
        <p class="text-muted small">Período: {{ $facturacionMensual->nombre_mes }} {{ $facturacionMensual->año }} — Sede: {{ $facturacionMensual->sede?->nombre ?? 'General' }}</p>
        <form action="{{ route('facturacion-mensual.update', $facturacionMensual) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Cantidad de alumnxs *</label>
                    <input type="number" name="cantidad_alumnos" class="form-control" min="0" value="{{ old('cantidad_alumnos', $facturacionMensual->cantidad_alumnos) }}" required>
                    @error('cantidad_alumnos')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Monto facturado *</label>
                    <input type="number" name="monto_facturado" class="form-control" step="0.01" min="0" value="{{ old('monto_facturado', $facturacionMensual->monto_facturado) }}" required>
                    @error('monto_facturado')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Monto previsto</label>
                    <input type="number" name="monto_previsto" class="form-control" step="0.01" min="0" value="{{ old('monto_previsto', $facturacionMensual->monto_previsto) }}">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Notas</label>
                <textarea name="notas" class="form-control" rows="2">{{ old('notas', $facturacionMensual->notas) }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary">Guardar</button>
            <a href="{{ route('facturacion-mensual.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>
@endsection
