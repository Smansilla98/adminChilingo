@extends('layouts.app')

@section('title', 'Nuevo bloque')
@section('page-title', 'Nuevo bloque')

@section('content')
<div class="card">
    <div class="card-header">Crear bloque</div>
    <div class="card-body">
        <form action="{{ route('bloques.store') }}" method="POST">
            @csrf
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="nombre" class="form-label">Nombre *</label>
                    <input type="text" class="form-control @error('nombre') is-invalid @enderror" id="nombre" name="nombre" value="{{ old('nombre') }}" required>
                    @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label for="año" class="form-label">Año (1-6) *</label>
                    <input type="number" class="form-control" id="año" name="año" value="{{ old('año', 1) }}" min="1" max="6" required>
                    @error('año')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label for="cantidad_max_alumnos" class="form-label">Cant. máx. personas *</label>
                    <input type="number" class="form-control" id="cantidad_max_alumnos" name="cantidad_max_alumnos" value="{{ old('cantidad_max_alumnos', 20) }}" min="1" required>
                    @error('cantidad_max_alumnos')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="profesor_id" class="form-label">Profesor</label>
                    <select class="form-select" id="profesor_id" name="profesor_id">
                        <option value="">Sin asignar</option>
                        @foreach($profesores as $p)
                        <option value="{{ $p->id }}" {{ old('profesor_id') == $p->id ? 'selected' : '' }}>{{ $p->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="corresponde_a" class="form-label">A quien corresponde el bloque</label>
                    <input type="text" class="form-control" id="corresponde_a" name="corresponde_a" value="{{ old('corresponde_a') }}" placeholder="Responsable o destinatario del bloque">
                    @error('corresponde_a')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Sede *</label>
                    <select class="form-select @error('sede_id') is-invalid @enderror" name="sede_id" required>
                        <option value="">Seleccionar...</option>
                        @foreach($sedes as $sede)
                        <option value="{{ $sede->id }}" {{ old('sede_id') == $sede->id ? 'selected' : '' }}>{{ $sede->nombre }}</option>
                        @endforeach
                    </select>
                    @error('sede_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Tambores del bloque</label>
                <div class="d-flex flex-wrap gap-2">
                    @foreach($tamboresDisponibles as $t)
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="tambores[]" value="{{ $t }}" id="tambor_{{ $loop->index }}" {{ in_array($t, old('tambores', [])) ? 'checked' : '' }}>
                        <label class="form-check-label" for="tambor_{{ $loop->index }}">{{ $t }}</label>
                    </div>
                    @endforeach
                </div>
                <small class="text-muted">Seleccione los tambores que usa este bloque</small>
            </div>
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" checked>
                    <label class="form-check-label" for="activo">Activo</label>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Guardar</button>
            <a href="{{ route('bloques.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>
@endsection
