@extends('layouts.app')

@section('title', 'Facturación por mes')
@section('page-title', 'Facturación por mes')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Facturación mensual</h5>
        <a href="{{ route('facturacion-mensual.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle"></i> Cargar mes</a>
    </div>
    <div class="card-body">
        <form method="GET" class="mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">Sede</label>
                    <select name="sede_id" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        @foreach($sedes as $s)
                        <option value="{{ $s->id }}" {{ request('sede_id') == $s->id ? 'selected' : '' }}>{{ $s->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Año</label>
                    <input type="number" name="año" class="form-control form-control-sm" value="{{ request('año') }}" min="2020" max="2030" placeholder="Año">
                </div>
                <div class="col-md-2"><button type="submit" class="btn btn-primary btn-sm">Filtrar</button></div>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Período</th>
                        <th>Sede</th>
                        <th>Cant. alumnxs</th>
                        <th>Facturado</th>
                        <th>Previsto</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($facturacion as $f)
                    <tr>
                        <td>{{ $f->nombre_mes }} {{ $f->año }}</td>
                        <td>{{ $f->sede?->nombre ?? 'General' }}</td>
                        <td>{{ $f->cantidad_alumnos }}</td>
                        <td>$ {{ number_format($f->monto_facturado, 2, ',', '.') }}</td>
                        <td>{{ $f->monto_previsto !== null ? '$ ' . number_format($f->monto_previsto, 2, ',', '.') : '—' }}</td>
                        <td>
                            <a href="{{ route('facturacion-mensual.edit', $f) }}" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center">No hay datos. <a href="{{ route('facturacion-mensual.create') }}">Cargar facturación</a></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $facturacion->withQueryString()->links() }}
    </div>
</div>
@endsection
