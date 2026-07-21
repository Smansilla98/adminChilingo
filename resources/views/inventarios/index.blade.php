@extends('layouts.app')

@section('title', 'Inventarios')
@section('page-title', 'Inventarios por sede')

@section('content')
<x-ito.list-page title="Inventarios" subtitle="Ítems por sede y propiedad">
    <x-slot:actions>
        <a href="{{ route('inventarios.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nuevo ítem</a>
    </x-slot:actions>

    <x-slot:toolbar>
        <form method="GET" class="ito-toolbar-filters w-100 d-flex flex-wrap align-items-end justify-content-between gap-2">
            <div class="d-flex flex-wrap gap-2 align-items-end flex-grow-1">
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
                        @foreach($tipos as $k => $label)
                            <option value="{{ $k }}" @selected(request('tipo') == $k)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ito-field">
                    <label>Propiedad</label>
                    <select name="propietario_tipo" class="form-select">
                        <option value="">Todas</option>
                        @foreach($propietarios as $k => $label)
                            <option value="{{ $k }}" @selected(request('propietario_tipo') == $k)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
            </div>
            <div class="ito-search">
                <i class="bi bi-search"></i>
                <input type="text" name="q" class="form-control" value="{{ request('q') }}" placeholder="Nombre, código, marca, modelo…">
            </div>
        </form>
    </x-slot:toolbar>

    <table class="ito-table">
        <thead>
            <tr>
                <th>Sede</th>
                <th>Tipo</th>
                <th>Nombre</th>
                <th>Propiedad</th>
                <th>Cant.</th>
                <th>Marca/Modelo</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $item)
                @php
                    $estadoLabel = \App\Models\InventarioItem::ESTADOS[$item->estado] ?? $item->estado;
                    $estadoTone = match ($item->estado) {
                        'nuevo', 'bueno' => 'success',
                        'regular', 'reparacion' => 'warning',
                        'baja' => 'danger',
                        default => 'neutral',
                    };
                    $nombreSub = collect([
                        $item->codigo ? 'Código: '.$item->codigo : null,
                        $item->utilitario ? 'Utilitario' : null,
                    ])->filter()->implode(' · ');
                @endphp
                <tr>
                    <td>{{ $item->sede?->nombre }}</td>
                    <td>{{ $item->tipo_label }}</td>
                    <td>
                        <x-ito.person :name="$item->nombre" :sub="$nombreSub ?: null" />
                    </td>
                    <td>
                        <x-ito.person
                            :name="$item->propietario_label"
                            :sub="$item->propietario_tipo === 'alumno' && $item->alumno ? $item->alumno->nombre_apellido : null"
                        />
                    </td>
                    <td class="ito-mono">
                        @if($item->es_consumible)
                            {{ number_format((float) $item->cantidad, 2, ',', '.') }} {{ $item->unidad ?? '' }}
                        @else
                            1 u
                        @endif
                    </td>
                    <td>{{ $item->marca ?? '—' }}@if($item->modelo) / {{ $item->modelo }}@endif</td>
                    <td>
                        <x-ito.status :tone="$estadoTone" :label="$estadoLabel" />
                    </td>
                    <td>
                        <x-ito.actions :id="'inv-'.$item->id">
                            <li><a class="dropdown-item" href="{{ route('inventarios.show', $item) }}"><i class="bi bi-eye"></i> Ver</a></li>
                            <li><a class="dropdown-item" href="{{ route('inventarios.edit', $item) }}"><i class="bi bi-pencil"></i> Editar</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('inventarios.destroy', $item) }}" method="POST" onsubmit="return confirm('¿Eliminar ítem?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash"></i> Eliminar</button>
                                </form>
                            </li>
                        </x-ito.actions>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="ito-empty">No hay ítems cargados. <a href="{{ route('inventarios.create') }}">Crear uno</a></td></tr>
            @endforelse
        </tbody>
    </table>

    <x-slot:footer>
        <div class="ito-footer-meta">@if(method_exists($items, 'total'))
            {{ $items->total() }} registros
        @endif
        </div>
        {{ $items->withQueryString()->links('pagination::bootstrap-5') }}
    </x-slot:footer>
</x-ito.list-page>
@endsection
