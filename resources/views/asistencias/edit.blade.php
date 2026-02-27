@extends('layouts.app')

@section('title', 'Editar asistencia')
@section('page-title', 'Editar asistencia')

@section('content')
<div class="card">
    <div class="card-header">Editar tipo de asistencia</div>
    <div class="card-body">
        <form action="{{ route('asistencias.update', $asistencia) }}" method="POST">
            @csrf
            @method('PUT')
            <p class="text-muted">{{ $asistencia->alumno->nombre_apellido ?? '-' }} — {{ $asistencia->fecha->format('d/m/Y') }} — Bloque: {{ $asistencia->bloque->nombre ?? '-' }}</p>
            @php
                $valorActual = $asistencia->tipo_asistencia;
                if ($valorActual === 'ausente') $valorActual = 'ausencia_injustificada';
                if ($valorActual === 'justificado') $valorActual = 'ausencia_justificada';
            @endphp
            <div class="mb-3">
                <label class="form-label">Tipo de asistencia</label>
                <select name="tipo_asistencia" class="form-select">
                    @foreach($tiposAsistencia as $valor => $etiqueta)
                    <option value="{{ $valor }}" {{ old('tipo_asistencia', $valorActual) == $valor ? 'selected' : '' }}>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Guardar</button>
            <a href="{{ route('asistencias.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>
@endsection
