@extends('layouts.app')

@section('title', 'Editar show')
@section('page-title', 'Editar show')

@section('content')
<div class="card shadow-sm">
    <div class="card-header py-3">Editar show</div>
    <div class="card-body">
        <form action="{{ route('shows.update', $show) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="row mb-3">
                <div class="col-md-8">
                    <label class="form-label">Título *</label>
                    <input type="text" name="titulo" class="form-control @error('titulo') is-invalid @enderror" value="{{ old('titulo', $show->titulo) }}" required>
                    @error('titulo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Lugar</label>
                    <input type="text" name="lugar" class="form-control" value="{{ old('lugar', $show->lugar) }}">
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Fecha *</label>
                    <input type="date" name="fecha" class="form-control" value="{{ old('fecha', $show->fecha->format('Y-m-d')) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Hora inicio</label>
                    <input type="time" name="hora_inicio" class="form-control" value="{{ old('hora_inicio', $show->hora_inicio ? $show->hora_inicio->format('H:i') : '') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Hora fin</label>
                    <input type="time" name="hora_fin" class="form-control" value="{{ old('hora_fin', $show->hora_fin ? $show->hora_fin->format('H:i') : '') }}">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Descripción</label>
                <textarea name="descripcion" class="form-control" rows="2">{{ old('descripcion', $show->descripcion) }}</textarea>
            </div>
            <div class="mb-3">
                <div class="form-check">
                    <input type="checkbox" name="convocatoria_abierta" value="1" class="form-check-input" id="convocatoria_abierta" {{ old('convocatoria_abierta', $show->convocatoria_abierta) ? 'checked' : '' }}>
                    <label class="form-check-label" for="convocatoria_abierta">Convocatoria abierta</label>
                </div>
            </div>
            <div class="mb-3" id="bloques-wrap">
                <label class="form-label">Bloques que participan</label>
                <select name="bloque_ids[]" class="form-select" multiple size="8">
                    @foreach($bloques as $b)
                    <option value="{{ $b->id }}" {{ in_array($b->id, old('bloque_ids', $show->bloques->pluck('id')->toArray())) ? 'selected' : '' }}>
                        {{ $b->nombre }} — {{ $b->sede->nombre ?? '' }} @if($b->profesor) ({{ $b->profesor->nombre }}) @endif
                    </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Guardar</button>
            <a href="{{ route('shows.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>
@endsection
