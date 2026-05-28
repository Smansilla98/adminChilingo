@extends('layouts.app')

@section('title', 'Nuevo profesor')
@section('page-title', 'Nuevo profesor')

@section('content')
<div class="card">
    <div class="card-header">Nuevo profesor</div>
    <div class="card-body">
        @include('partials.form-ayuda-intro', ['text' => 'Nombre y contacto alcanzan para empezar. Después indicá en qué bloques y sedes participa.'])
        <form action="{{ route('profesores.store') }}" method="POST">
            @csrf
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Nombre *</label>
                    <input type="text" name="nombre" class="form-control @error('nombre') is-invalid @enderror" value="{{ old('nombre') }}" required>
                    @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Teléfono</label>
                    <input type="text" name="telefono" class="form-control @error('telefono') is-invalid @enderror" value="{{ old('telefono') }}">
                    @error('telefono')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Correo electrónico</label>
                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <div class="form-check">
                        <input type="checkbox" name="activo" class="form-check-input" id="activo" value="1" {{ old('activo', true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="activo">Activo</label>
                    </div>
                </div>
            </div>
            @include('profesores._form_bloques', ['bloquesParaAsignar' => $bloquesParaAsignar])
            @include('profesores._form_sedes_roles', ['sedes' => $sedes ?? collect()])
            <button type="submit" class="btn btn-primary">Guardar</button>
            <a href="{{ route('profesores.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>
@endsection
