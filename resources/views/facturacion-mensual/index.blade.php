@extends('layouts.app')

@section('title', 'Facturación por mes')
@section('page-title', 'Facturación por mes')

@section('content')
<x-ito.list-page title="Facturación mensual" subtitle="Montos facturados y previstos por período">
    <x-slot:actions>
        <a href="{{ route('facturacion-mensual.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Cargar mes</a>
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
                <label>Año</label>
                <input type="number" name="año" class="form-control" value="{{ request('año') }}" min="2020" max="2030" placeholder="Año">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
        </form>
    </x-slot:toolbar>

    <table class="ito-table">
        <thead>
            <tr>
                <th>Período</th>
                <th>Sede</th>
                <th>Cant. alumnxs</th>
                <th>Facturado</th>
                <th>Previsto</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($facturacion as $f)
                <tr>
                    <td class="fw-semibold">{{ $f->nombre_mes }} {{ $f->año }}</td>
                    <td>{{ $f->sede?->nombre ?? 'General' }}</td>
                    <td class="ito-mono">{{ $f->cantidad_alumnos }}</td>
                    <td class="ito-mono">$ {{ number_format($f->monto_facturado, 2, ',', '.') }}</td>
                    <td class="ito-mono">{{ $f->monto_previsto !== null ? '$ ' . number_format($f->monto_previsto, 2, ',', '.') : '—' }}</td>
                    <td>
                        <x-ito.actions :id="'fact-'.$f->id">
                            <li><a class="dropdown-item" href="{{ route('facturacion-mensual.edit', $f) }}"><i class="bi bi-pencil"></i> Editar</a></li>
                        </x-ito.actions>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="ito-empty">No hay datos. <a href="{{ route('facturacion-mensual.create') }}">Cargar facturación</a></td></tr>
            @endforelse
        </tbody>
    </table>

    <x-slot:footer>
        <div class="ito-footer-meta">@if(method_exists($facturacion, 'total'))
            {{ $facturacion->total() }} registros
        @endif
        </div>
        {{ $facturacion->withQueryString()->links('pagination::bootstrap-5') }}
    </x-slot:footer>
</x-ito.list-page>
@endsection
