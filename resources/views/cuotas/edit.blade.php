@extends('layouts.app')

@section('title', 'Editar cuota')
@section('page-title', 'Editar cuota')

@section('content')
<div class="card">
    <div class="card-header">Editar cuota</div>
    <div class="card-body">
        <form action="{{ route('cuotas.update', $cuota) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Nombre *</label>
                    <input type="text" name="nombre" class="form-control @error('nombre') is-invalid @enderror" value="{{ old('nombre', $cuota->nombre) }}" required>
                    @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Año *</label>
                    <input type="number" name="año" class="form-control" value="{{ old('año', $cuota->año) }}" min="2020" max="2030" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Mes</label>
                    <select name="mes" class="form-select">
                        <option value="">Sin mes</option>
                        @foreach(\App\Models\FacturacionMensual::nombresMeses() as $n => $nombre)
                        <option value="{{ $n }}" {{ old('mes', $cuota->mes) == $n ? 'selected' : '' }}>{{ $nombre }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Monto *</label>
                    <input type="number" name="monto" class="form-control" step="0.01" min="0" value="{{ old('monto', $cuota->monto) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Descripción</label>
                    <input type="text" name="descripcion" class="form-control" value="{{ old('descripcion', $cuota->descripcion) }}">
                </div>
            </div>
            <div class="mb-3">
                <div class="form-check">
                    <input type="checkbox" name="activo" class="form-check-input" id="activo" value="1" {{ old('activo', $cuota->activo) ? 'checked' : '' }}>
                    <label class="form-check-label" for="activo">Activa</label>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Guardar</button>
            <a href="{{ route('cuotas.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>
@endsection
