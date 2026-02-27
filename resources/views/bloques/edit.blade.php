@extends('layouts.app')

@section('title', 'Editar bloque')
@section('page-title', 'Editar bloque')

@section('content')
<div class="card">
    <div class="card-header">Editar bloque</div>
    <div class="card-body">
        <form action="{{ route('bloques.update', $bloque) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="nombre" class="form-label">Nombre *</label>
                    <input type="text" class="form-control @error('nombre') is-invalid @enderror" id="nombre" name="nombre" value="{{ old('nombre', $bloque->nombre) }}" required>
                    @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label for="año" class="form-label">Año (1-6) *</label>
                    <input type="number" class="form-control" id="año" name="año" value="{{ old('año', $bloque->año) }}" min="1" max="6" required>
                    @error('año')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label for="cantidad_max_alumnos" class="form-label">Cant. máx. personas *</label>
                    <input type="number" class="form-control" id="cantidad_max_alumnos" name="cantidad_max_alumnos" value="{{ old('cantidad_max_alumnos', $bloque->cantidad_max_alumnos) }}" min="1" required>
                    @error('cantidad_max_alumnos')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="profesor_id" class="form-label">Profesor</label>
                    <select class="form-select" id="profesor_id" name="profesor_id">
                        <option value="">Sin asignar</option>
                        @foreach($profesores as $p)
                        <option value="{{ $p->id }}" {{ old('profesor_id', $bloque->profesor_id) == $p->id ? 'selected' : '' }}>{{ $p->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="corresponde_a" class="form-label">A quien corresponde el bloque</label>
                    <input type="text" class="form-control" id="corresponde_a" name="corresponde_a" value="{{ old('corresponde_a', $bloque->corresponde_a) }}" placeholder="Responsable o destinatario del bloque">
                    @error('corresponde_a')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Sede *</label>
                    <select class="form-select @error('sede_id') is-invalid @enderror" name="sede_id" required>
                        @foreach($sedes as $sede)
                        <option value="{{ $sede->id }}" {{ old('sede_id', $bloque->sede_id) == $sede->id ? 'selected' : '' }}>{{ $sede->nombre }}</option>
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
                        <input class="form-check-input" type="checkbox" name="tambores[]" value="{{ $t }}" id="tambor_{{ $loop->index }}" {{ in_array($t, old('tambores', $bloque->tambores ?? [])) ? 'checked' : '' }}>
                        <label class="form-check-label" for="tambor_{{ $loop->index }}">{{ $t }}</label>
                    </div>
                    @endforeach
                </div>
            </div>
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" {{ old('activo', $bloque->activo) ? 'checked' : '' }}>
                    <label class="form-check-label" for="activo">Activo</label>
                </div>
            </div>
            <hr>
            <h6 class="mb-2">Días y horarios del bloque</h6>
            @if($bloque->horarios->isNotEmpty())
            <table class="table table-sm table-bordered mb-3">
                <thead><tr><th>Día</th><th>Hora inicio</th><th>Hora fin</th><th></th></tr></thead>
                <tbody>
                    @foreach($bloque->horarios as $h)
                    <tr>
                        <td>{{ \App\Models\BloqueHorario::DIAS_SEMANA[$h->dia_semana] ?? $h->dia_semana }}</td>
                        <td>{{ \Carbon\Carbon::parse($h->hora_inicio)->format('H:i') }}</td>
                        <td>{{ \Carbon\Carbon::parse($h->hora_fin)->format('H:i') }}</td>
                        <td>
                            <form action="{{ route('bloque-horarios.destroy', $h) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Quitar este horario?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Quitar</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @else
            <p class="text-muted small mb-2">Sin horarios cargados. Agregue día y horario abajo.</p>
            @endif
            <form action="{{ route('bloques.horarios.store', $bloque) }}" method="POST" class="row g-2 align-items-end mb-3">
                @csrf
                <div class="col-md-3">
                    <label class="form-label small">Día</label>
                    <select name="dia_semana" class="form-select form-select-sm" required>
                        @foreach(\App\Models\BloqueHorario::DIAS_SEMANA as $n => $nombre)
                        <option value="{{ $n }}">{{ $nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Desde</label>
                    <input type="time" name="hora_inicio" class="form-control form-control-sm" value="18:00" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Hasta</label>
                    <input type="time" name="hora_fin" class="form-control form-control-sm" value="19:30" required>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-outline-primary">Agregar horario</button>
                </div>
            </form>
            <button type="submit" class="btn btn-primary">Guardar bloque</button>
            <a href="{{ route('bloques.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>
@endsection
