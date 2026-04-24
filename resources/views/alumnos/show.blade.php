@extends('layouts.app')

@section('title', 'Alumno')
@section('page-title', 'Alumno')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">{{ $alumno->nombre_apellido }}</h5>
        <div>
            <a href="{{ route('alumnos.edit', $alumno) }}" class="btn btn-warning btn-sm"><i class="bi bi-pencil"></i> Editar</a>
            <a href="{{ route('alumnos.index') }}" class="btn btn-secondary btn-sm">Volver</a>
        </div>
    </div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-4">
                <strong>DNI:</strong> {{ $alumno->dni }}
            </div>
            <div class="col-md-4">
                <strong>Fecha de nacimiento:</strong> {{ $alumno->fecha_nacimiento ? $alumno->fecha_nacimiento->format('d/m/Y') : '—' }}
            </div>
            <div class="col-md-4">
                <strong>Edad:</strong> {{ $alumno->edad }} años
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-4">
                <strong>Teléfono:</strong> {{ $alumno->telefono ?? '—' }}
            </div>
            <div class="col-md-4">
                <strong>Instrumento principal:</strong> {{ $alumno->instrumento_principal ?? '—' }}
            </div>
            <div class="col-md-4">
                <strong>Instrumento secundario:</strong> {{ $alumno->instrumento_secundario ?? '—' }}
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-4">
                <strong>Tipo de instrumento (tambor):</strong> {{ $alumno->tipo_tambor ?? '—' }}
            </div>
            <div class="col-md-4">
                <strong>Procedencia:</strong> {{ $alumno->tambor_procedencia ?? '—' }}
            </div>
            <div class="col-md-4">
                <strong>Sede:</strong> {{ $alumno->sede->nombre ?? '—' }}
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-4">
                <strong>Estado:</strong> {{ $alumno->activo ? 'Activo' : 'Inactivo' }}
            </div>
        </div>
        @if($alumno->bloques->isNotEmpty())
        <h6 class="mt-3">Bloques</h6>
        <ul class="list-group">
            @foreach($alumno->bloques as $bloque)
            <li class="list-group-item d-flex justify-content-between align-items-center">
                {{ $bloque->nombre ?? 'Bloque' }}
                @if($bloque->profesor ?? null)
                <span class="badge bg-secondary">{{ $bloque->profesor->nombre }}</span>
                @endif
            </li>
            @endforeach
        </ul>
        @elseif($alumno->bloque)
        <h6 class="mt-3">Bloque</h6>
        <p class="mb-0">{{ $alumno->bloque->nombre }} @if($alumno->bloque->profesor ?? null)({{ $alumno->bloque->profesor->nombre }})@endif</p>
        @endif

        <h6 class="mt-4">Estado de cuenta</h6>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>Período</th>
                        <th>Monto</th>
                        <th>Fecha de pago</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($estadoCuenta ?? collect()) as $row)
                    <tr>
                        <td>{{ $row['periodo'] }}</td>
                        <td>{{ $row['monto'] ? '$ ' . number_format($row['monto'], 2, ',', '.') : '—' }}</td>
                        <td>
                            @if($row['fecha_pago'])
                                {{ \Carbon\Carbon::parse($row['fecha_pago'])->format('d/m/Y') }}
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-{{ $row['estado_color'] }} {{ $row['estado_color'] === 'warning' ? 'text-dark' : '' }}">
                                {{ $row['estado'] }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted">No hay cuotas asociadas para este alumno.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(isset($alumno->asistencias) && $alumno->asistencias->isNotEmpty())
        <h6 class="mt-3">Últimas asistencias</h6>
        <ul class="list-group">
            @foreach($alumno->asistencias->take(10) as $a)
            <li class="list-group-item">{{ $a->fecha ? \Carbon\Carbon::parse($a->fecha)->format('d/m/Y') : '' }} — {{ $a->presente ? 'Presente' : 'Ausente' }}</li>
            @endforeach
        </ul>
        @endif
    </div>
</div>
@endsection
