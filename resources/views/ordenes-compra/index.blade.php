@extends('layouts.app')

@section('title', 'Órdenes de compra')
@section('page-title', 'Órdenes de compra')

@section('content')
<x-ito.list-page title="Órdenes de compra" subtitle="Solicitudes y seguimiento de compras">
    <x-slot:actions>
        <a href="{{ route('ordenes-compra.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nueva orden</a>
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
                <label>Estado</label>
                <select name="estado" class="form-select">
                    <option value="">Todos</option>
                    @foreach($estados as $k => $label)
                        <option value="{{ $k }}" @selected(request('estado') == $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
        </form>
    </x-slot:toolbar>

    <table class="ito-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Sede</th>
                <th>Motivo</th>
                <th>Estado</th>
                <th>Fecha objetivo</th>
                <th>Total estimado</th>
                <th>Creada por</th>
                <th>Creada el</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($ordenes as $o)
                @php
                    $estadoLabel = \App\Models\OrdenCompra::ESTADOS[$o->estado] ?? $o->estado;
                    $estadoTone = match ($o->estado) {
                        'aprobada', 'recibida' => 'success',
                        'enviada' => 'info',
                        'cancelada' => 'danger',
                        default => 'neutral',
                    };
                @endphp
                <tr>
                    <td class="ito-mono">{{ $o->id }}</td>
                    <td>{{ $o->sede?->nombre }}</td>
                    <td>{{ \App\Models\OrdenCompra::MOTIVOS[$o->motivo] ?? $o->motivo }}</td>
                    <td>
                        <x-ito.status :tone="$estadoTone" :label="$estadoLabel" />
                    </td>
                    <td class="ito-mono">{{ $o->fecha_objetivo?->format('d/m/Y') ?? '—' }}</td>
                    <td class="ito-mono">{{ $o->total_estimado ? '$ ' . number_format($o->total_estimado, 2, ',', '.') : '—' }}</td>
                    <td>{{ $o->creador?->name ?? '—' }}</td>
                    <td class="ito-mono">{{ $o->created_at?->format('d/m/Y') }}</td>
                    <td>
                        <x-ito.actions :id="'oc-'.$o->id">
                            <li><a class="dropdown-item" href="{{ route('ordenes-compra.show', $o) }}"><i class="bi bi-eye"></i> Ver</a></li>
                            <li><a class="dropdown-item" href="{{ route('ordenes-compra.edit', $o) }}"><i class="bi bi-pencil"></i> Editar</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('ordenes-compra.destroy', $o) }}" method="POST" onsubmit="return confirm('¿Eliminar esta orden?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash"></i> Eliminar</button>
                                </form>
                            </li>
                        </x-ito.actions>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="ito-empty">No hay órdenes de compra.</td></tr>
            @endforelse
        </tbody>
    </table>

    <x-slot:footer>
        <div class="ito-footer-meta">@if(method_exists($ordenes, 'total'))
            {{ $ordenes->total() }} registros
        @endif
        </div>
        {{ $ordenes->withQueryString()->links('pagination::bootstrap-5') }}
    </x-slot:footer>
</x-ito.list-page>
@endsection
