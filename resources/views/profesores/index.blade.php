@extends('layouts.app')

@section('title', 'Profesores')
@section('page-title', 'Profesores')

@section('content')
<x-ito.list-page title="Profesores" subtitle="Cuerpo docente y vínculos a bloques">
    <x-slot:actions>
        <a href="{{ route('profesores.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nuevo profesor</a>
    </x-slot:actions>

    <table class="ito-table">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Teléfono</th>
                <th>Correo</th>
                <th>Bloques</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($profesores as $profesor)
                <tr>
                    <td><x-ito.person :name="$profesor->nombre" /></td>
                    <td class="ito-mono">{{ $profesor->telefono ?? '—' }}</td>
                    <td>{{ $profesor->email ?? '—' }}</td>
                    <td>{{ $profesor->bloques_count ?? 0 }}</td>
                    <td>
                        <x-ito.status :tone="$profesor->activo ? 'success' : 'neutral'" :label="$profesor->activo ? 'Activo' : 'Inactivo'" />
                    </td>
                    <td>
                        <x-ito.actions :id="'prof-'.$profesor->id">
                            <li><a class="dropdown-item" href="{{ route('profesores.show', $profesor) }}"><i class="bi bi-eye"></i> Ver</a></li>
                            <li><a class="dropdown-item" href="{{ route('profesores.edit', $profesor) }}"><i class="bi bi-pencil"></i> Editar</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('profesores.destroy', $profesor) }}" method="POST" onsubmit="return confirm('¿Eliminar este profesor?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash"></i> Eliminar</button>
                                </form>
                            </li>
                        </x-ito.actions>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="ito-empty">No hay profesores. <a href="{{ route('profesores.create') }}">Crear el primero</a>.</td></tr>
            @endforelse
        </tbody>
    </table>

    <x-slot:footer>
        <div class="ito-footer-meta">@if(method_exists($profesores, 'total')){{ $profesores->total() }} registros@endif</div>
        {{ $profesores->links('pagination::bootstrap-5') }}
    </x-slot:footer>
</x-ito.list-page>
@endsection
