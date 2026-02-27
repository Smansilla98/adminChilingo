@extends('layouts.app')

@section('title', 'Cargar asistencias')
@section('page-title', 'Cargar asistencias')

@section('content')
<div class="card">
    <div class="card-header">Seleccionar bloque y fecha</div>
    <div class="card-body">
        <form method="GET" class="mb-4">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Bloque</label>
                    <select name="bloque_id" class="form-select" required>
                        <option value="">Seleccionar bloque...</option>
                        @foreach($bloques as $b)
                        <option value="{{ $b->id }}" {{ (request('bloque_id') == $b->id || (isset($bloque) && $bloque->id == $b->id)) ? 'selected' : '' }}>{{ $b->nombre }} — {{ $b->sede->nombre ?? '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Fecha</label>
                    <input type="date" name="fecha" class="form-control" value="{{ request('fecha', date('Y-m-d')) }}" required>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Continuar</button>
                </div>
            </div>
        </form>

        @if(isset($bloque))
        <hr>
        <form action="{{ route('asistencias.store') }}" method="POST">
            @csrf
            <input type="hidden" name="bloque_id" value="{{ $bloque->id }}">
            <input type="hidden" name="fecha" value="{{ request('fecha', date('Y-m-d')) }}">

            <p class="text-muted">Bloque: <strong>{{ $bloque->nombre }}</strong>. A quien corresponde: <strong>{{ $bloque->corresponde_a ?? $bloque->profesor->nombre ?? '-' }}</strong>. Fecha: <strong>{{ request('fecha', date('Y-m-d')) }}</strong></p>

            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Alumno</th>
                            <th>Instrumento</th>
                            <th>Tipo de asistencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($bloque->alumnos->where('activo', true) as $alumno)
                        <tr>
                            <td>
                                {{ $alumno->nombre_apellido }}
                                <input type="hidden" name="asistencias[{{ $loop->index }}][alumno_id]" value="{{ $alumno->id }}">
                            </td>
                            <td>{{ $alumno->instrumento_principal }}</td>
                            <td>
                                <select name="asistencias[{{ $loop->index }}][tipo_asistencia]" class="form-select form-select-sm">
                                    @foreach($tiposAsistencia as $valor => $etiqueta)
                                    <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($bloque->alumnos->where('activo', true)->isEmpty())
            <p class="text-warning">No hay alumnos activos en este bloque.</p>
            @else
            <button type="submit" class="btn btn-primary">Guardar asistencias</button>
            @endif
        </form>
        @endif
    </div>
</div>
@endsection
