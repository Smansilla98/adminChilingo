@extends('layouts.app')

@section('title', 'Sedes')
@section('page-title', 'Sedes')

@section('content')
<x-ito.list-page title="Sedes" subtitle="Espacios físicos de la escuela">
    <x-slot:actions>
        <a href="{{ route('sedes.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nueva sede</a>
    </x-slot:actions>

    <table class="ito-table">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Dirección</th>
                <th>Propiedad</th>
                <th>Alquiler/mes</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($sedes as $sede)
                <tr>
                    <td class="fw-semibold">{{ $sede->nombre }}</td>
                    <td>{{ $sede->direccion ?? '—' }}</td>
                    <td>{{ $sede->tipo_propiedad === 'propia' ? 'Propia' : ($sede->tipo_propiedad === 'alquilada' ? 'Alquilada' : ucfirst($sede->tipo_propiedad ?? '—')) }}</td>
                    <td class="ito-mono">
                        @if($sede->costo_alquiler_mensual)
                            $ {{ number_format($sede->costo_alquiler_mensual, 2, ',', '.') }}
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        <x-ito.status :tone="$sede->activo ? 'success' : 'neutral'" :label="$sede->activo ? 'Activa' : 'Inactiva'" />
                    </td>
                    <td>
                        <x-ito.actions :id="'sede-'.$sede->id">
                            <li><a class="dropdown-item" href="{{ route('sedes.show', $sede) }}"><i class="bi bi-eye"></i> Ver</a></li>
                            <li><a class="dropdown-item" href="{{ route('sedes.edit', $sede) }}"><i class="bi bi-pencil"></i> Editar</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('sedes.destroy', $sede) }}" method="POST" data-confirm="¿Eliminar esta sede?">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash"></i> Eliminar</button>
                                </form>
                            </li>
                        </x-ito.actions>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="ito-empty">No hay sedes. <a href="{{ route('sedes.create') }}">Crear la primera</a>.</td></tr>
            @endforelse
        </tbody>
    </table>

    <x-slot:footer>
        <div class="ito-footer-meta">@if(method_exists($sedes, 'total'))
            {{ $sedes->total() }} registros
        @endif
        </div>
        {{ $sedes->links('pagination::bootstrap-5') }}
    </x-slot:footer>
</x-ito.list-page>
@endsection
