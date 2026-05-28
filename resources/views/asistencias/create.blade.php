@extends('layouts.app')

@section('title', 'Cargar asistencias')
@section('page-title', 'Cargar asistencias')

@section('content')
<div class="card">
    <div class="card-header">Elegí bloque y fecha</div>
    <div class="card-body">
        @include('partials.form-ayuda-intro', ['text' => 'Primero elegí el bloque y el día. Después marcás presente o ausente a cada alumno.'])
        <form method="GET" class="mb-4">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Bloque</label>
                    <select name="bloque_id" class="form-select" required>
                        <option value="">Elegí bloque…</option>
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
        <form action="{{ auth()->user()?->isAdmin() ? route('asistencias.store') : route('profesor.asistencias.store') }}" method="POST">
            @csrf
            <input type="hidden" name="bloque_id" value="{{ $bloque->id }}">
            <input type="hidden" name="fecha" value="{{ request('fecha', date('Y-m-d')) }}">

            <p class="text-muted small mb-3">Bloque <strong>{{ $bloque->nombre }}</strong> — fecha <strong>{{ request('fecha', date('Y-m-d')) }}</strong>. Marcá quién vino y guardá.</p>

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
