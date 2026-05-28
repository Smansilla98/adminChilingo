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
        @include('partials.form-ayuda-intro', ['text' => 'Subí una planilla con muchos alumnos de una vez. Elegí la sede antes de enviar el archivo.'])
        <div class="alert alert-info mb-3">
            <div class="fw-semibold mb-1">Cómo tiene que venir el archivo</div>
            <div class="small text-muted">
                La primera fila son los títulos de columna. Usá estos nombres (pueden ir con o sin tilde):
                <strong>Nombre y Apellido</strong>, <strong>DNI</strong>, <strong>Fecha de Nacimiento</strong>,
                <strong>Número de teléfono</strong> y dos columnas <strong>Tambor</strong> (una para el instrumento y otra para de dónde es el tambor).
            </div>
        </div>

        <form action="{{ route('alumnos.import.store') }}" method="POST" enctype="multipart/form-data" class="row g-3">
            @csrf

            <div class="col-12">
                <label class="form-label">Archivo</label>
                <input type="file" name="archivo" class="form-control @error('archivo') is-invalid @enderror" accept=".csv,.txt,.xlsx,.xls" required>
                @error('archivo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Excel (.xlsx, .xls) o planilla de texto (.csv, .txt).</div>
            </div>

            <div class="col-md-6">
                <label class="form-label">Sede destino</label>
                <select name="sede_id" class="form-select @error('sede_id') is-invalid @enderror" required>
                    <option value="">Elegí sede…</option>
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

