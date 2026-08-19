@extends('layouts.app')

@section('title', 'Insumos Villa Gesell')
@section('page-title', 'Insumos')

@section('content')
@include('villa-gesell.partials.nav')

<x-ito.list-page
    title="Insumos de la gira"
    subtitle="ABM de lo que hay que llevar o comprar. El total entra al plan de gastos."
>
    <x-slot:actions>
        <a href="{{ route('villa-gesell.insumos.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nuevo insumo</a>
    </x-slot:actions>

    <table class="ito-table">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Categoría</th>
                <th>Cantidad</th>
                <th class="text-end">Unitario</th>
                <th class="text-end">Total</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($insumos as $row)
                <tr>
                    <td>{{ $row->nombre }}</td>
                    <td>{{ \App\Models\VillaGesellInsumo::CATEGORIAS[$row->categoria] ?? $row->categoria }}</td>
                    <td class="ito-mono">{{ rtrim(rtrim(number_format($row->cantidad, 2, ',', '.'), '0'), ',') }} {{ $row->unidad }}</td>
                    <td class="ito-mono text-end">$ {{ number_format($row->costo_unitario, 0, ',', '.') }}</td>
                    <td class="ito-mono text-end">$ {{ number_format($row->costoTotal(), 0, ',', '.') }}</td>
                    <td>
                        <x-ito.actions :id="'vg-insumo-'.$row->id">
                            <li><a class="dropdown-item" href="{{ route('villa-gesell.insumos.edit', $row) }}">Editar</a></li>
                            <li>
                                <form action="{{ route('villa-gesell.insumos.destroy', $row) }}" method="POST" data-confirm="¿Eliminar este insumo?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="dropdown-item text-danger" type="submit">Eliminar</button>
                                </form>
                            </li>
                        </x-ito.actions>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">Sin insumos. Cargá remeras, parches, comida, etc.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <th colspan="4" class="text-end">Total insumos</th>
                <th class="ito-mono text-end">$ {{ number_format($total, 0, ',', '.') }}</th>
                <th></th>
            </tr>
        </tfoot>
    </table>
</x-ito.list-page>
@endsection
