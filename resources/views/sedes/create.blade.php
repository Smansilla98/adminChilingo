@extends('layouts.app')

@section('title', 'Nueva sede')
@section('page-title', 'Nueva sede')

@section('content')
<div class="card">
    <div class="card-header">Nueva sede</div>
    <div class="card-body">
        <form action="{{ route('sedes.store') }}" method="POST">
            @csrf
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Nombre *</label>
                    <input type="text" name="nombre" class="form-control @error('nombre') is-invalid @enderror" value="{{ old('nombre') }}" required>
                    @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Dirección</label>
                    <input type="text" name="direccion" class="form-control @error('direccion') is-invalid @enderror" value="{{ old('direccion') }}">
                    @error('direccion')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Tipo de propiedad</label>
                    <select name="tipo_propiedad" class="form-select @error('tipo_propiedad') is-invalid @enderror">
                        <option value="alquilada" {{ old('tipo_propiedad', 'alquilada') === 'alquilada' ? 'selected' : '' }}>Alquilada</option>
                        <option value="propia" {{ old('tipo_propiedad') === 'propia' ? 'selected' : '' }}>Propia</option>
                        <option value="compartida" {{ old('tipo_propiedad') === 'compartida' ? 'selected' : '' }}>Compartida</option>
                        <option value="otro" {{ old('tipo_propiedad') === 'otro' ? 'selected' : '' }}>Otro</option>
                    </select>
                    @error('tipo_propiedad')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Costo alquiler mensual</label>
                    <input type="number" name="costo_alquiler_mensual" class="form-control @error('costo_alquiler_mensual') is-invalid @enderror" step="0.01" min="0" value="{{ old('costo_alquiler_mensual') }}" placeholder="Solo si es alquilada">
                    @error('costo_alquiler_mensual')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check">
                        <input type="checkbox" name="activo" class="form-check-input" id="activo" value="1" {{ old('activo', true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="activo">Activa</label>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Guardar</button>
            <a href="{{ route('sedes.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>
@endsection
