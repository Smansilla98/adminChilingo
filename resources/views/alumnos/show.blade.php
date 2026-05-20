@extends('layouts.app')

@section('title', 'Alumno')
@section('page-title', 'Alumno')

@section('content')
@php $isAdmin = auth()->user()->isAdmin(); @endphp
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">{{ $alumno->nombre_apellido }}</h5>
        <div>
            @if($isAdmin)
            <a href="{{ route('alumnos.edit', $alumno) }}" class="btn btn-warning btn-sm"><i class="bi bi-pencil"></i> Editar</a>
            <a href="{{ route('alumnos.index') }}" class="btn btn-secondary btn-sm">Volver</a>
            @else
            <a href="{{ route('profesor.alumnos') }}" class="btn btn-secondary btn-sm">Volver a mis alumnos</a>
            @endif
        </div>
    </div>
    <div class="card-body">
        @if(!empty($profesorPerfil))
        <div class="alert alert-info py-2 small mb-3">
            <i class="bi bi-person-badge"></i> También tiene perfil de <strong>profesor</strong>:
            <a href="{{ route('profesores.show', $profesorPerfil) }}">{{ $profesorPerfil->nombre }}</a>
        </div>
        @endif

        <div class="row mb-3">
            <div class="col-md-4">
                <strong>DNI:</strong> {{ $alumno->dni ?? '—' }}
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
                <strong>Sede principal:</strong> {{ $alumno->sede->nombre ?? '—' }}
            </div>
            <div class="col-md-4">
                <strong>Estado:</strong> {{ $alumno->activo ? 'Activo' : 'Inactivo' }}
            </div>
        </div>

        <h6 class="mt-2">Instrumentos</h6>
        <ul class="list-group mb-3">
            <li class="list-group-item d-flex justify-content-between">
                <span>Principal</span>
                <strong>{{ $alumno->instrumento_principal ?? '—' }}</strong>
            </li>
            <li class="list-group-item d-flex justify-content-between">
                <span>Secundario</span>
                <strong>{{ $alumno->instrumento_secundario ?? '—' }}</strong>
            </li>
            <li class="list-group-item d-flex justify-content-between">
                <span>Tipo de tambor</span>
                <strong>{{ $alumno->tipo_tambor ?? '—' }}</strong>
            </li>
            <li class="list-group-item d-flex justify-content-between">
                <span>Procedencia del instrumento</span>
                <strong>{{ $alumno->tambor_procedencia ?? '—' }}</strong>
            </li>
        </ul>

        <h6 class="mt-2">Bloques</h6>
        @if($alumno->bloques->isNotEmpty())
        <ul class="list-group mb-3">
            @foreach($alumno->bloques as $bloque)
            <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>
                    {{ $bloque->nombre ?? 'Bloque' }}
                    @if($bloque->pivot->es_principal ?? false)
                    <span class="badge bg-warning text-dark ms-1">Principal</span>
                    @endif
                </span>
                <span>
                    @if($bloque->sede ?? null)
                    <span class="badge bg-secondary">{{ $bloque->sede->nombre }}</span>
                    @endif
                    @if($bloque->profesor ?? null)
                    <span class="badge bg-primary">{{ $bloque->profesor->nombre }}</span>
                    @endif
                </span>
            </li>
            @endforeach
        </ul>
        @elseif($alumno->bloque)
        <p class="mb-3">{{ $alumno->bloque->nombre }} @if($alumno->bloque->profesor)({{ $alumno->bloque->profesor->nombre }})@endif</p>
        @else
        <p class="text-muted mb-3">Sin bloques asignados.</p>
        @endif

        <h6 class="mt-4">Historial de pagos de cuotas</h6>
        <div class="table-responsive mb-4">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>Fecha pago</th>
                        <th>Cuota / período</th>
                        <th>Monto pagado</th>
                        <th>Abono docente</th>
                        @if($isAdmin)
                        <th></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse(($historialPagos ?? collect()) as $det)
                    <tr>
                        <td>{{ $det->pago?->fecha_pago ? \Carbon\Carbon::parse($det->pago->fecha_pago)->format('d/m/Y') : '—' }}</td>
                        <td>
                            {{ $det->cuota?->nombre ?? 'Cuota' }}
                            @if($det->cuota?->mes && $det->cuota?->año)
                            <span class="text-muted small">({{ str_pad((string) $det->cuota->mes, 2, '0', STR_PAD_LEFT) }}/{{ $det->cuota->año }})</span>
                            @endif
                        </td>
                        <td>$ {{ number_format((float) $det->monto, 2, ',', '.') }}</td>
                        <td>
                            @if($det->abono_profesor !== null)
                            $ {{ number_format((float) $det->abono_profesor, 2, ',', '.') }}
                            @else
                            —
                            @endif
                        </td>
                        @if($isAdmin && $det->pago)
                        <td><a href="{{ route('pagos.show', $det->pago) }}" class="btn btn-sm btn-outline-secondary">Ver pago</a></td>
                        @elseif($isAdmin)
                        <td></td>
                        @endif
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ $isAdmin ? 5 : 4 }}" class="text-center text-muted">Aún no hay pagos registrados para este alumno.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <h6 class="mt-2">Estado de cuenta (cuotas aplicables)</h6>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>Período</th>
                        <th>Monto cuota</th>
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
        <h6 class="mt-4">Últimas asistencias</h6>
        <ul class="list-group">
            @foreach($alumno->asistencias->take(10) as $a)
            <li class="list-group-item">{{ $a->fecha ? \Carbon\Carbon::parse($a->fecha)->format('d/m/Y') : '' }} — {{ $a->presente ? 'Presente' : 'Ausente' }}</li>
            @endforeach
        </ul>
        @endif
    </div>
</div>
@endsection
