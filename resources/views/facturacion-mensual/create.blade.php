@extends('layouts.app')

@section('title', 'Cargar facturación mensual')
@section('page-title', 'Facturación por mes — Cargar')

@section('content')
<div class="card">
    <div class="card-header">Nueva facturación mensual</div>
    <div class="card-body">
        <form action="{{ route('facturacion-mensual.store') }}" method="POST">
            @csrf
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Sede (opcional)</label>
                    <select name="sede_id" class="form-select">
                        <option value="">General / Todas</option>
                        @foreach($sedes as $s)
                        <option value="{{ $s->id }}" {{ old('sede_id') == $s->id ? 'selected' : '' }}>{{ $s->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Año *</label>
                    <input type="number" name="año" class="form-control" value="{{ old('año', date('Y')) }}" min="2020" max="2030" required>
                    @error('año')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Mes *</label>
                    <select name="mes" class="form-select @error('mes') is-invalid @enderror" required>
                        @foreach($meses as $n => $nombre)
                        <option value="{{ $n }}" {{ old('mes') == $n ? 'selected' : '' }}>{{ $nombre }}</option>
                        @endforeach
                    </select>
                    @error('mes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Cantidad de alumnxs *</label>
                    <input type="number" name="cantidad_alumnos" class="form-control @error('cantidad_alumnos') is-invalid @enderror" min="0" value="{{ old('cantidad_alumnos', 0) }}" required>
                    @error('cantidad_alumnos')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Monto facturado *</label>
                    <input type="number" name="monto_facturado" class="form-control @error('monto_facturado') is-invalid @enderror" step="0.01" min="0" value="{{ old('monto_facturado') }}" required>
                    @error('monto_facturado')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Monto previsto (opcional)</label>
                    <input type="number" name="monto_previsto" class="form-control" step="0.01" min="0" value="{{ old('monto_previsto') }}">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Notas</label>
                <textarea name="notas" class="form-control" rows="2">{{ old('notas') }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary">Guardar</button>
            <a href="{{ route('facturacion-mensual.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>
@endsection
