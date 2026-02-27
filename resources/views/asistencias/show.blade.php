@extends('layouts.app')

@section('title', 'Ver asistencia')
@section('page-title', 'Detalle de asistencia')

@section('content')
<div class="card">
    <div class="card-header">Asistencia</div>
    <div class="card-body">
        <dl class="row">
            <dt class="col-sm-3">Fecha</dt>
            <dd class="col-sm-9">{{ $asistencia->fecha->format('d/m/Y') }}</dd>

            <dt class="col-sm-3">Alumno</dt>
            <dd class="col-sm-9">{{ $asistencia->alumno->nombre_apellido ?? '-' }}</dd>

            <dt class="col-sm-3">Bloque</dt>
            <dd class="col-sm-9">{{ $asistencia->bloque->nombre ?? '-' }}</dd>

            <dt class="col-sm-3">Tipo de asistencia</dt>
            <dd class="col-sm-9">
                <span class="badge bg-{{ $asistencia->tipo_asistencia === 'presente' || $asistencia->tipo_asistencia === 'tarde' ? 'success' : ($asistencia->tipo_asistencia === 'justificado' ? 'info' : 'secondary') }}">
                    {{ \App\Models\Asistencia::TIPOS_ASISTENCIA[$asistencia->tipo_asistencia] ?? $asistencia->tipo_asistencia }}
                </span>
            </dd>
        </dl>
        <a href="{{ route('asistencias.edit', $asistencia) }}" class="btn btn-warning">Editar</a>
        <a href="{{ route('asistencias.index') }}" class="btn btn-secondary">Volver</a>
    </div>
</div>
@endsection
