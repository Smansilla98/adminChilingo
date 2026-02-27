@extends('layouts.app')

@section('title', 'Pagos')
@section('page-title', 'Pagos (trazabilidad)')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Registro de pagos</h5>
        <a href="{{ route('pagos.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle"></i> Registrar pago</a>
    </div>
    <div class="card-body">
        <form method="GET" class="mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small">Alumno</label>
                    <select name="alumno_id" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        @foreach($alumnos as $a)
                        <option value="{{ $a->id }}" {{ request('alumno_id') == $a->id ? 'selected' : '' }}>{{ $a->nombre_apellido }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Cuota</label>
                    <select name="cuota_id" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        @foreach($cuotas as $c)
                        <option value="{{ $c->id }}" {{ request('cuota_id') == $c->id ? 'selected' : '' }}>{{ $c->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Desde</label>
                    <input type="date" name="desde" class="form-control form-control-sm" value="{{ request('desde') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Hasta</label>
                    <input type="date" name="hasta" class="form-control form-control-sm" value="{{ request('hasta') }}">
                </div>
                <div class="col-md-2"><button type="submit" class="btn btn-primary btn-sm">Filtrar</button></div>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Cuota</th>
                        <th>Alumnos</th>
                        <th>Monto total</th>
                        <th>Comprobante</th>
                        <th>Registrado por</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pagos as $p)
                    <tr>
                        <td>{{ $p->fecha_pago->format('d/m/Y') }}</td>
                        <td>{{ $p->detalles->first()?->cuota?->nombre ?? '-' }}</td>
                        <td>{{ $p->detalles->pluck('alumno.nombre_apellido')->unique()->join(', ') }}</td>
                        <td>$ {{ number_format($p->monto_total, 2, ',', '.') }}</td>
                        <td>
                            @if($p->comprobante_path)
                            <a href="{{ route('pagos.comprobante', $p) }}" class="btn btn-sm btn-outline-secondary" target="_blank"><i class="bi bi-file-pdf"></i> PDF</a>
                            @else
                            —
                            @endif
                        </td>
                        <td>{{ $p->registradoPor?->name ?? '-' }}</td>
                        <td>
                            <a href="{{ route('pagos.show', $p) }}" class="btn btn-sm btn-info"><i class="bi bi-eye"></i></a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center">No hay pagos. <a href="{{ route('pagos.create') }}">Registrar uno</a></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $pagos->withQueryString()->links() }}
    </div>
</div>
@endsection
