@extends('layouts.app')

@section('title', 'Cuotas')
@section('page-title', 'Cuotas')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Cuotas</h5>
        <a href="{{ route('cuotas.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle"></i> Nueva cuota</a>
    </div>
    <div class="card-body">
        <form method="GET" class="mb-3">
            <div class="row g-2">
                <div class="col-md-3">
                    <input type="number" name="año" class="form-control" placeholder="Año" value="{{ request('año') }}" min="2020" max="2030">
                </div>
                <div class="col-md-2"><button type="submit" class="btn btn-primary">Filtrar</button></div>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Año</th>
                        <th>Mes</th>
                        <th>Monto</th>
                        <th>Activo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cuotas as $c)
                    <tr>
                        <td>{{ $c->nombre }}</td>
                        <td>{{ $c->año }}</td>
                        <td>{{ $c->nombre_mes ?? '-' }}</td>
                        <td>$ {{ number_format($c->monto, 2, ',', '.') }}</td>
                        <td>{{ $c->activo ? 'Sí' : 'No' }}</td>
                        <td>
                            <a href="{{ route('cuotas.show', $c) }}" class="btn btn-sm btn-info"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('cuotas.edit', $c) }}" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                            <form action="{{ route('cuotas.destroy', $c) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar cuota?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center">No hay cuotas. <a href="{{ route('cuotas.create') }}">Crear una</a></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $cuotas->withQueryString()->links() }}
    </div>
</div>
@endsection
