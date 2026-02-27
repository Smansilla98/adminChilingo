@extends('layouts.app')

@section('title', 'Nueva cuota')
@section('page-title', 'Nueva cuota')

@section('content')
<div class="card">
    <div class="card-header">Nueva cuota</div>
    <div class="card-body">
        <form action="{{ route('cuotas.store') }}" method="POST">
            @csrf
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Nombre *</label>
                    <input type="text" name="nombre" class="form-control @error('nombre') is-invalid @enderror" value="{{ old('nombre') }}" placeholder="Ej. Cuota Marzo 2025" required>
                    @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Año *</label>
                    <input type="number" name="año" class="form-control" value="{{ old('año', date('Y')) }}" min="2020" max="2030" required>
                    @error('año')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Mes (opcional)</label>
                    <select name="mes" class="form-select">
                        <option value="">Sin mes</option>
                        @foreach(\App\Models\FacturacionMensual::nombresMeses() as $n => $nombre)
                        <option value="{{ $n }}" {{ old('mes') == $n ? 'selected' : '' }}>{{ $nombre }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Monto *</label>
                    <input type="number" name="monto" class="form-control" step="0.01" min="0" value="{{ old('monto') }}" required>
                    @error('monto')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Descripción</label>
                    <input type="text" name="descripcion" class="form-control" value="{{ old('descripcion') }}">
                </div>
            </div>
            <div class="mb-3">
                <div class="form-check">
                    <input type="checkbox" name="activo" class="form-check-input" id="activo" value="1" checked>
                    <label class="form-check-label" for="activo">Activa</label>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Guardar</button>
            <a href="{{ route('cuotas.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>
@endsection
