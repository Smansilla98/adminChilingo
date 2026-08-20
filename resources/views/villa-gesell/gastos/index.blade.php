@extends('layouts.app')

@section('title', 'Gastos Villa Gesell')
@section('page-title', 'Gastos')

@section('content')
@include('villa-gesell.partials.nav')

<x-ito.list-page
    title="Gastos de la gira"
    subtitle="Cargá el valor por día (o un valor único). En el plan, lo diario se multiplica por los {{ $dias }} días."
>
    <x-slot:actions>
        <a href="{{ route('villa-gesell.gastos.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nuevo gasto</a>
        <a href="{{ route('villa-gesell.plan') }}" class="btn btn-outline-primary btn-sm">Ver plan</a>
    </x-slot:actions>

    <table class="ito-table">
        <thead>
            <tr>
                <th>Tipo</th>
                <th>Concepto</th>
                <th>Modo</th>
                <th class="text-end">Valor</th>
                <th class="text-end">× {{ $dias }} días</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($gastos as $row)
                <tr>
                    <td>{{ \App\Models\VillaGesellGasto::TIPOS[$row->tipo] ?? $row->tipo }}</td>
                    <td>{{ $row->concepto }}</td>
                    <td>{{ \App\Models\VillaGesellGasto::MODOS[$row->modo] ?? $row->modo }}</td>
                    <td class="ito-mono text-end">
                        $ {{ number_format($row->monto, 0, ',', '.') }}
                        @if($row->modo === 'por_dia' || $row->tipo === 'diario')
                            <small class="d-block text-muted">/ día</small>
                        @endif
                    </td>
                    <td class="ito-mono text-end">$ {{ number_format($row->proyectado($dias), 0, ',', '.') }}</td>
                    <td>
                        <x-ito.actions :id="'vg-gasto-'.$row->id">
                            <li><a class="dropdown-item" href="{{ route('villa-gesell.gastos.edit', $row) }}">Editar</a></li>
                            <li>
                                <form action="{{ route('villa-gesell.gastos.destroy', $row) }}" method="POST" data-confirm="¿Eliminar este gasto?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="dropdown-item text-danger" type="submit">Eliminar</button>
                                </form>
                            </li>
                        </x-ito.actions>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">Sin gastos cargados.</td></tr>
            @endforelse
        </tbody>
    </table>
</x-ito.list-page>
@endsection
