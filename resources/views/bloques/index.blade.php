@extends('layouts.app')

@section('title', 'Bloques')
@section('page-title', 'Bloques')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Listado de Bloques</h5>
        <a href="{{ route('bloques.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle"></i> Nuevo bloque
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Año</th>
                        <th>A quien corresponde</th>
                        <th>Tambores</th>
                        <th>Cant. máx. personas</th>
                        <th>Sede</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bloques as $bloque)
                    <tr>
                        <td>{{ $bloque->nombre }}</td>
                        <td>{{ $bloque->año }}</td>
                        <td>{{ $bloque->corresponde_a ?? ($bloque->profesor->nombre ?? '-') }}</td>
                        <td>
                            @if($bloque->tambores && count($bloque->tambores) > 0)
                                {{ implode(', ', $bloque->tambores) }}
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $bloque->cantidad_max_alumnos }}</td>
                        <td>{{ $bloque->sede->nombre ?? '-' }}</td>
                        <td>
                            <a href="{{ route('bloques.show', $bloque) }}" class="btn btn-sm btn-info"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('bloques.edit', $bloque) }}" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                            <form action="{{ route('bloques.destroy', $bloque) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar bloque?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center">No hay bloques</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $bloques->links() }}
    </div>
</div>
@endsection
