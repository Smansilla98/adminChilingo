@extends('layouts.app')

@section('title', 'Sedes')
@section('page-title', 'Sedes')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Sedes</h5>
        <a href="{{ route('sedes.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle"></i> Nueva sede</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Dirección</th>
                        <th>Propiedad</th>
                        <th>Alquiler/mes</th>
                        <th>Activo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sedes as $sede)
                    <tr>
                        <td>{{ $sede->nombre }}</td>
                        <td>{{ $sede->direccion ?? '—' }}</td>
                        <td>{{ $sede->tipo_propiedad === 'propia' ? 'Propia' : ($sede->tipo_propiedad === 'alquilada' ? 'Alquilada' : ucfirst($sede->tipo_propiedad ?? '—')) }}</td>
                        <td>@if($sede->costo_alquiler_mensual) $ {{ number_format($sede->costo_alquiler_mensual, 2, ',', '.') }} @else — @endif</td>
                        <td>{{ $sede->activo ? 'Sí' : 'No' }}</td>
                        <td>
                            <a href="{{ route('sedes.show', $sede) }}" class="btn btn-sm btn-info" title="Ver"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('sedes.edit', $sede) }}" class="btn btn-sm btn-warning" title="Editar"><i class="bi bi-pencil"></i></a>
                            <form action="{{ route('sedes.destroy', $sede) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar esta sede?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted">No hay sedes. <a href="{{ route('sedes.create') }}">Crear la primera</a>.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $sedes->links() }}
    </div>
</div>
@endsection
