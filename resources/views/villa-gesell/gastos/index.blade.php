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
        @if($papelera)
            <a href="{{ route('villa-gesell.gastos.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver a los gastos</a>
        @else
            <a href="{{ route('villa-gesell.gastos.index', ['papelera' => 1]) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-trash"></i> Papelera</a>
        @endif
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
                        @if($papelera)
                            <small class="d-block text-muted">Borrado {{ $row->deleted_at?->format('d/m/Y') }}{{ $row->eliminadoPor ? ' por '.$row->eliminadoPor->name : '' }}{{ $row->motivo_eliminacion ? ': '.$row->motivo_eliminacion : '' }}</small>
                            <form action="{{ route('villa-gesell.gastos.restaurar', $row->id) }}" method="POST" class="d-inline">
                                @csrf
                                <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-arrow-counterclockwise"></i> Restaurar</button>
                            </form>
                        @else
                        <x-ito.actions :id="'vg-gasto-'.$row->id">
                            <li><a class="dropdown-item" href="{{ route('villa-gesell.gastos.edit', $row) }}">Editar</a></li>
                            <li>
                                <form action="{{ route('villa-gesell.gastos.destroy', $row) }}" method="POST" data-confirm="¿Mandar este gasto a la papelera?" class="px-3 py-1">
                                    @csrf
                                    @method('DELETE')
                                    <input type="text" name="motivo" class="form-control form-control-sm mb-1" maxlength="500" placeholder="Motivo (opcional)">
                                    <button class="btn btn-sm btn-outline-danger w-100" type="submit">Eliminar</button>
                                </form>
                            </li>
                        </x-ito.actions>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">{{ $papelera ? 'La papelera está vacía.' : 'Sin gastos cargados.' }}</td></tr>
            @endforelse
        </tbody>
    </table>
</x-ito.list-page>
@endsection
