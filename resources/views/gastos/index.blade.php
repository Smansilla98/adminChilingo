@extends('layouts.app')

@section('title', 'Gastos')
@section('page-title', 'Gastos')

@section('content')
<x-ito.list-page title="Gastos" subtitle="Registro de gastos operativos">
    <x-slot:actions>
        <a href="{{ route('gastos.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nuevo gasto</a>
    </x-slot:actions>

    <x-slot:toolbar>
        <form method="GET" class="ito-toolbar-filters w-100 d-flex flex-wrap align-items-end gap-2">
            <div class="ito-field">
                <label>Sede</label>
                <select name="sede_id" class="form-select">
                    <option value="">Todas</option>
                    @foreach($sedes as $s)
                        <option value="{{ $s->id }}" @selected(request('sede_id') == $s->id)>{{ $s->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ito-field">
                <label>Tipo</label>
                <select name="tipo" class="form-select">
                    <option value="">Todos</option>
                    @foreach(\App\Models\Gasto::TIPOS as $k => $v)
                        <option value="{{ $k }}" @selected(request('tipo') === $k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ito-field">
                <label>Desde</label>
                <input type="date" name="desde" class="form-control" value="{{ request('desde') }}">
            </div>
            <div class="ito-field">
                <label>Hasta</label>
                <input type="date" name="hasta" class="form-control" value="{{ request('hasta') }}">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
        </form>
    </x-slot:toolbar>

    <table class="ito-table">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Subtipo</th>
                <th>Descripción</th>
                <th>Sede / Bloque</th>
                <th class="text-end">Monto</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($gastos as $g)
                <tr>
                    <td class="ito-mono">{{ $g->fecha->format('d/m/Y') }}</td>
                    <td>{{ \App\Models\Gasto::TIPOS[$g->tipo] ?? $g->tipo }}</td>
                    <td>{{ $g->subtipo ?: '—' }}</td>
                    <td>{{ Str::limit($g->descripcion, 40) ?: '—' }}</td>
                    <td>
                        @if($g->sede || $g->bloque)
                            <x-ito.person
                                :name="$g->sede?->nombre ?? '—'"
                                :sub="$g->bloque?->nombre"
                            />
                        @else
                            —
                        @endif
                    </td>
                    <td class="ito-mono text-end">$ {{ number_format($g->monto, 2, ',', '.') }}</td>
                    <td>
                        <x-ito.actions :id="'gasto-'.$g->id">
                            <li><a class="dropdown-item" href="{{ route('gastos.show', $g) }}"><i class="bi bi-eye"></i> Ver</a></li>
                            <li><a class="dropdown-item" href="{{ route('gastos.edit', $g) }}"><i class="bi bi-pencil"></i> Editar</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('gastos.destroy', $g) }}" method="POST" onsubmit="return confirm('¿Eliminar este gasto?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash"></i> Eliminar</button>
                                </form>
                            </li>
                        </x-ito.actions>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="ito-empty">No hay gastos registrados. <a href="{{ route('gastos.create') }}">Registrar el primero</a>.</td></tr>
            @endforelse
        </tbody>
    </table>

    <x-slot:footer>
        <div class="ito-footer-meta">@if(method_exists($gastos, 'total')){{ $gastos->total() }} registros@endif</div>
        {{ $gastos->withQueryString()->links('pagination::bootstrap-5') }}
    </x-slot:footer>
</x-ito.list-page>
@endsection
