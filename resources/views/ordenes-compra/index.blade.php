@extends('layouts.app')

@section('title', 'Órdenes de compra')
@section('page-title', 'Órdenes de compra')

@section('content')
<div class="card">
    <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>Órdenes de compra</div>
        <a href="{{ route('ordenes-compra.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle"></i> Nueva orden
        </a>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-3">
                <label class="form-label small">Sede</label>
                <select name="sede_id" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    @foreach($sedes as $s)
                    <option value="{{ $s->id }}" {{ request('sede_id') == $s->id ? 'selected' : '' }}>{{ $s->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Estado</label>
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($estados as $k => $label)
                    <option value="{{ $k }}" {{ request('estado') == $k ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-sm btn-outline-primary">Filtrar</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Sede</th>
                        <th>Motivo</th>
                        <th>Estado</th>
                        <th>Fecha objetivo</th>
                        <th>Total estimado</th>
                        <th>Creada por</th>
                        <th>Creada el</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ordenes as $o)
                    <tr>
                        <td>{{ $o->id }}</td>
                        <td>{{ $o->sede?->nombre }}</td>
                        <td>{{ \App\Models\OrdenCompra::MOTIVOS[$o->motivo] ?? $o->motivo }}</td>
                        <td>{{ \App\Models\OrdenCompra::ESTADOS[$o->estado] ?? $o->estado }}</td>
                        <td>{{ $o->fecha_objetivo?->format('d/m/Y') ?? '—' }}</td>
                        <td>{{ $o->total_estimado ? '$ ' . number_format($o->total_estimado, 2, ',', '.') : '—' }}</td>
                        <td>{{ $o->creador?->name ?? '—' }}</td>
                        <td>{{ $o->created_at?->format('d/m/Y') }}</td>
                        <td class="text-end">
                            <a href="{{ route('ordenes-compra.show', $o) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('ordenes-compra.edit', $o) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                            <form action="{{ route('ordenes-compra.destroy', $o) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar esta orden?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center text-muted">No hay órdenes de compra.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $ordenes->withQueryString()->links() }}
    </div>
</div>
@endsection

