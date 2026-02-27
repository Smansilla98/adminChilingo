@extends('layouts.app')

@section('title', 'Nuevo evento')
@section('page-title', 'Nuevo evento')

@section('content')
<div class="card">
    <div class="card-header">Nuevo evento</div>
    <div class="card-body">
        <form action="{{ route('eventos.store') }}" method="POST">
            @csrf
            <div class="row mb-3">
                <div class="col-md-8">
                    <label class="form-label">Título *</label>
                    <input type="text" name="titulo" class="form-control @error('titulo') is-invalid @enderror" value="{{ old('titulo') }}" required>
                    @error('titulo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tipo *</label>
                    <select name="tipo_evento" class="form-select @error('tipo_evento') is-invalid @enderror" required>
                        @foreach($tiposEvento as $t)
                        <option value="{{ $t }}" {{ old('tipo_evento') === $t ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $t)) }}</option>
                        @endforeach
                    </select>
                    @error('tipo_evento')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Descripción</label>
                <textarea name="descripcion" class="form-control @error('descripcion') is-invalid @enderror" rows="2">{{ old('descripcion') }}</textarea>
                @error('descripcion')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Fecha *</label>
                    <input type="date" name="fecha" class="form-control @error('fecha') is-invalid @enderror" value="{{ old('fecha', date('Y-m-d')) }}" required>
                    @error('fecha')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Hora inicio</label>
                    <input type="time" name="hora_inicio" class="form-control @error('hora_inicio') is-invalid @enderror" value="{{ old('hora_inicio') }}">
                    @error('hora_inicio')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Hora fin</label>
                    <input type="time" name="hora_fin" class="form-control @error('hora_fin') is-invalid @enderror" value="{{ old('hora_fin') }}">
                    @error('hora_fin')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label">Cant. personas</label>
                    <input type="number" name="cantidad_personas" class="form-control @error('cantidad_personas') is-invalid @enderror" min="0" value="{{ old('cantidad_personas') }}">
                    @error('cantidad_personas')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Sede</label>
                    <select name="sede_id" class="form-select @error('sede_id') is-invalid @enderror">
                        <option value="">— Sin sede —</option>
                        @foreach($sedes as $s)
                        <option value="{{ $s->id }}" {{ old('sede_id') == $s->id ? 'selected' : '' }}>{{ $s->nombre }}</option>
                        @endforeach
                    </select>
                    @error('sede_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Profesor</label>
                    <select name="profesor_id" class="form-select @error('profesor_id') is-invalid @enderror">
                        <option value="">— Sin profesor —</option>
                        @foreach($profesores as $p)
                        <option value="{{ $p->id }}" {{ old('profesor_id') == $p->id ? 'selected' : '' }}>{{ $p->nombre }}</option>
                        @endforeach
                    </select>
                    @error('profesor_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Bloque</label>
                    <select name="bloque_id" class="form-select @error('bloque_id') is-invalid @enderror">
                        <option value="">— Sin bloque —</option>
                        @foreach($bloques as $b)
                        <option value="{{ $b->id }}" {{ old('bloque_id') == $b->id ? 'selected' : '' }}>{{ $b->nombre }} ({{ $b->sede?->nombre ?? '-' }})</option>
                        @endforeach
                    </select>
                    @error('bloque_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Guardar</button>
            <a href="{{ route('eventos.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>
@endsection
