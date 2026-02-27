@extends('layouts.app')

@section('title', 'Editar Alumno')
@section('page-title', 'Editar Alumno')

@section('content')
<div class="card">
    <div class="card-header">Editar Alumno</div>
    <div class="card-body">
        <form action="{{ route('alumnos.update', $alumno) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="nombre_apellido" class="form-label">Nombre y Apellido *</label>
                    <input type="text" class="form-control @error('nombre_apellido') is-invalid @enderror" 
                           id="nombre_apellido" name="nombre_apellido" value="{{ old('nombre_apellido', $alumno->nombre_apellido) }}" required>
                    @error('nombre_apellido')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="dni" class="form-label">DNI *</label>
                    <input type="text" class="form-control @error('dni') is-invalid @enderror" 
                           id="dni" name="dni" value="{{ old('dni', $alumno->dni) }}" required>
                    @error('dni')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento *</label>
                    <input type="date" class="form-control @error('fecha_nacimiento') is-invalid @enderror" 
                           id="fecha_nacimiento" name="fecha_nacimiento" 
                           value="{{ old('fecha_nacimiento', $alumno->fecha_nacimiento->format('Y-m-d')) }}" required>
                    @error('fecha_nacimiento')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="telefono" class="form-label">Teléfono</label>
                    <input type="text" class="form-control @error('telefono') is-invalid @enderror" 
                           id="telefono" name="telefono" value="{{ old('telefono', $alumno->telefono) }}">
                    @error('telefono')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="instrumento_principal" class="form-label">Instrumento Principal *</label>
                    <select class="form-select @error('instrumento_principal') is-invalid @enderror" 
                            id="instrumento_principal" name="instrumento_principal" required>
                        <option value="">Seleccionar...</option>
                        @foreach($instrumentos as $instrumento)
                        <option value="{{ $instrumento }}" {{ old('instrumento_principal', $alumno->instrumento_principal) == $instrumento ? 'selected' : '' }}>
                            {{ $instrumento }}
                        </option>
                        @endforeach
                    </select>
                    @error('instrumento_principal')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="instrumento_secundario" class="form-label">Instrumento Secundario</label>
                    <select class="form-select @error('instrumento_secundario') is-invalid @enderror" 
                            id="instrumento_secundario" name="instrumento_secundario">
                        <option value="">Ninguno</option>
                        @foreach($instrumentos as $instrumento)
                        <option value="{{ $instrumento }}" {{ old('instrumento_secundario', $alumno->instrumento_secundario) == $instrumento ? 'selected' : '' }}>
                            {{ $instrumento }}
                        </option>
                        @endforeach
                    </select>
                    @error('instrumento_secundario')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="tipo_tambor" class="form-label">Tipo de Tambor *</label>
                    <select class="form-select @error('tipo_tambor') is-invalid @enderror" 
                            id="tipo_tambor" name="tipo_tambor" required>
                        <option value="Sede" {{ old('tipo_tambor', $alumno->tipo_tambor) == 'Sede' ? 'selected' : '' }}>Sede</option>
                        <option value="Propio" {{ old('tipo_tambor', $alumno->tipo_tambor) == 'Propio' ? 'selected' : '' }}>Propio</option>
                    </select>
                    @error('tipo_tambor')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label for="sede_id" class="form-label">Sede *</label>
                    <select class="form-select @error('sede_id') is-invalid @enderror" 
                            id="sede_id" name="sede_id" required>
                        <option value="">Seleccionar...</option>
                        @foreach($sedes as $sede)
                        <option value="{{ $sede->id }}" {{ old('sede_id', $alumno->sede_id) == $sede->id ? 'selected' : '' }}>
                            {{ $sede->nombre }}
                        </option>
                        @endforeach
                    </select>
                    @error('sede_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="bloque_id" class="form-label">Bloque</label>
                    <select class="form-select @error('bloque_id') is-invalid @enderror" 
                            id="bloque_id" name="bloque_id">
                        <option value="">Sin asignar</option>
                        @foreach($bloques as $bloque)
                        <option value="{{ $bloque->id }}" {{ old('bloque_id', $alumno->bloque_id) == $bloque->id ? 'selected' : '' }}>
                            {{ $bloque->nombre }} - {{ $bloque->sede->nombre }}
                        </option>
                        @endforeach
                    </select>
                    @error('bloque_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" 
                           {{ old('activo', $alumno->activo) ? 'checked' : '' }}>
                    <label class="form-check-label" for="activo">Activo</label>
                </div>
            </div>
            <div class="d-flex justify-content-between">
                <a href="{{ route('alumnos.index') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Actualizar</button>
            </div>
        </form>
    </div>
</div>
@endsection

