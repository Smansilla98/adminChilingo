@extends('layouts.app')

@section('title', 'Importar alumnos')
@section('page-title', 'Importación masiva de alumnos')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Importar alumnos (CSV / Excel)</h5>
        <a href="{{ route('alumnos.index') }}" class="btn btn-sm btn-outline-secondary">Volver</a>
    </div>
    <div class="card-body">
        <div class="alert alert-info mb-3">
            <div class="fw-semibold mb-1">Formato esperado (encabezados)</div>
            <div class="small text-muted">
                Se reconocen estas columnas (con o sin tildes/espacios): <code>Nombre y Apellido</code>, <code>DNI</code>, <code>Fecha de Nacimiento</code>,
                <code>Número de teléfono</code>, <code>Tambor</code> (tipo) y <code>Tambor</code> (procedencia).
            </div>
        </div>

        <form action="{{ route('alumnos.import.store') }}" method="POST" enctype="multipart/form-data" class="row g-3">
            @csrf

            <div class="col-12">
                <label class="form-label">Archivo</label>
                <input type="file" name="archivo" class="form-control @error('archivo') is-invalid @enderror" accept=".csv,.txt,.xlsx,.xls" required>
                @error('archivo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Soporta <code>.csv</code>, <code>.txt</code>, <code>.xlsx</code>, <code>.xls</code>.</div>
            </div>

            <div class="col-md-6">
                <label class="form-label">Sede destino</label>
                <select name="sede_id" class="form-select @error('sede_id') is-invalid @enderror" required>
                    <option value="">Seleccionar sede…</option>
                    @foreach($sedes as $sede)
                        <option value="{{ $sede->id }}" {{ old('sede_id') == $sede->id ? 'selected' : '' }}>{{ $sede->nombre }}</option>
                    @endforeach
                </select>
                @error('sede_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6">
                <label class="form-label">Bloque (opcional)</label>
                <select name="bloque_id" class="form-select @error('bloque_id') is-invalid @enderror">
                    <option value="">No asignar bloque</option>
                    @foreach($bloques as $bloque)
                        <option value="{{ $bloque->id }}" {{ old('bloque_id') == $bloque->id ? 'selected' : '' }}>{{ $bloque->nombre }}</option>
                    @endforeach
                </select>
                @error('bloque_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary">
                    <i class="bi bi-cloud-arrow-up"></i> Importar
                </button>
                <a href="{{ route('alumnos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection

