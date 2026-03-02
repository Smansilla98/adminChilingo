@extends('layouts.app')

@section('title', 'Profesores')
@section('page-title', 'Profesores')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Listado de Profesores</h5>
        <a href="{{ route('profesores.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle"></i> Nuevo profesor</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Teléfono</th>
                        <th>Correo</th>
                        <th>Bloques</th>
                        <th>Activo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($profesores as $profesor)
                    <tr>
                        <td>{{ $profesor->nombre }}</td>
                        <td>{{ $profesor->telefono ?? '—' }}</td>
                        <td>{{ $profesor->email ?? '—' }}</td>
                        <td>{{ $profesor->bloques->count() ?? 0 }}</td>
                        <td>{{ $profesor->activo ? 'Sí' : 'No' }}</td>
                        <td>
                            <a href="{{ route('profesores.show', $profesor) }}" class="btn btn-sm btn-info" title="Ver"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('profesores.edit', $profesor) }}" class="btn btn-sm btn-warning" title="Editar"><i class="bi bi-pencil"></i></a>
                            <form action="{{ route('profesores.destroy', $profesor) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este profesor?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted">No hay profesores. <a href="{{ route('profesores.create') }}">Crear el primero</a>.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $profesores->links() }}
    </div>
</div>
@endsection
