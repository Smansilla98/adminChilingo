@extends('layouts.app')

@php($editando = $persona->exists)
@section('title', $editando ? 'Editar persona' : 'Nueva persona')
@section('page-title', $editando ? 'Editar persona' : 'Nueva persona')

@section('content')
<x-ito.shell-page
    :title="$editando ? $persona->nombre_completo : 'Nueva persona'"
    eyebrow="Personas"
    subtitle="Datos personales. Se comparten con todas sus fichas (alumno, docente, cuenta)."
>
    <form method="POST" action="{{ $editando ? route('personas.update', $persona) : route('personas.store') }}">
        @csrf
        @if($editando) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="nombre">Nombre *</label>
                <input id="nombre" name="nombre" class="form-control @error('nombre') is-invalid @enderror" value="{{ old('nombre', $persona->nombre) }}" required autocomplete="given-name">
                @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="apellido">Apellido</label>
                <input id="apellido" name="apellido" class="form-control @error('apellido') is-invalid @enderror" value="{{ old('apellido', $persona->apellido) }}" autocomplete="family-name">
                @error('apellido')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="dni">DNI</label>
                <input id="dni" name="dni" inputmode="numeric" class="form-control @error('dni') is-invalid @enderror" value="{{ old('dni', $persona->dni) }}">
                @error('dni')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="fecha_nacimiento">Fecha de nacimiento</label>
                <input id="fecha_nacimiento" type="date" name="fecha_nacimiento" class="form-control @error('fecha_nacimiento') is-invalid @enderror" value="{{ old('fecha_nacimiento', $persona->fecha_nacimiento?->toDateString()) }}">
                @error('fecha_nacimiento')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="estado">Estado</label>
                <select id="estado" name="estado" class="form-select">
                    @foreach(\App\Models\Persona::ESTADOS as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected(old('estado', $persona->estado) === $valor)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="telefono">Teléfono</label>
                <input id="telefono" type="tel" name="telefono" class="form-control @error('telefono') is-invalid @enderror" value="{{ old('telefono', $persona->telefono) }}" autocomplete="tel">
                @error('telefono')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="email">Email</label>
                <input id="email" type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $persona->email) }}" autocomplete="email">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label class="form-label" for="direccion">Dirección</label>
                <input id="direccion" name="direccion" class="form-control" value="{{ old('direccion', $persona->direccion) }}" autocomplete="street-address">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="contacto_emergencia_nombre">Contacto de emergencia</label>
                <input id="contacto_emergencia_nombre" name="contacto_emergencia_nombre" class="form-control" value="{{ old('contacto_emergencia_nombre', $persona->contacto_emergencia_nombre) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="contacto_emergencia_telefono">Teléfono de emergencia</label>
                <input id="contacto_emergencia_telefono" type="tel" name="contacto_emergencia_telefono" class="form-control" value="{{ old('contacto_emergencia_telefono', $persona->contacto_emergencia_telefono) }}">
            </div>
            <div class="col-12">
                <label class="form-label" for="observaciones">Observaciones</label>
                <textarea id="observaciones" name="observaciones" rows="3" class="form-control">{{ old('observaciones', $persona->observaciones) }}</textarea>
            </div>
        </div>

        <div class="d-flex gap-2 mt-4">
            <button class="btn btn-primary">{{ $editando ? 'Guardar cambios' : 'Crear persona' }}</button>
            <a href="{{ $editando ? route('personas.show', $persona) : route('personas.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>
</x-ito.shell-page>
@endsection
