@extends('layouts.app')

@section('title', 'Bloques')
@section('page-title', 'Bloques')

@section('content')
<x-ito.list-page title="Bloques" subtitle="Grupos de ensayo por sede y año">
    <x-slot:actions>
        <a href="{{ route('bloques.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nuevo bloque</a>
    </x-slot:actions>

    <table class="ito-table">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Año</th>
                <th>A quien corresponde</th>
                <th>Tambores</th>
                <th>Cupo</th>
                <th>Sede</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($bloques as $bloque)
                <tr>
                    <td class="fw-semibold">{{ $bloque->nombre }}</td>
                    <td class="ito-mono">{{ $bloque->año }}</td>
                    <td>{{ $bloque->corresponde_a ?? ($bloque->profesor->nombre ?? '—') }}</td>
                    <td>{{ ($bloque->tambores && count($bloque->tambores) > 0) ? implode(', ', $bloque->tambores) : '—' }}</td>
                    <td class="ito-mono">{{ $bloque->cantidad_max_alumnos }}</td>
                    <td>{{ $bloque->sede->nombre ?? '—' }}</td>
                    <td>
                        <x-ito.actions :id="'bloque-'.$bloque->id">
                            <li><a class="dropdown-item" href="{{ route('bloques.show', $bloque) }}"><i class="bi bi-eye"></i> Ver</a></li>
                            <li><a class="dropdown-item" href="{{ route('bloques.edit', $bloque) }}"><i class="bi bi-pencil"></i> Editar</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('bloques.destroy', $bloque) }}" method="POST" onsubmit="return confirm('¿Eliminar bloque?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash"></i> Eliminar</button>
                                </form>
                            </li>
                        </x-ito.actions>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="ito-empty">No hay bloques</td></tr>
            @endforelse
        </tbody>
    </table>

    <x-slot:footer>
        <div class="ito-footer-meta">@if(method_exists($bloques, 'total')){{ $bloques->total() }} registros@endif</div>
        {{ $bloques->links('pagination::bootstrap-5') }}
    </x-slot:footer>
</x-ito.list-page>
@endsection
