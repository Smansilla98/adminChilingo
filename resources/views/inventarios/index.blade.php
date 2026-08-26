@extends('layouts.app')

@section('title', 'Inventarios')
@section('page-title', 'Inventarios por sede')

@section('content')
<x-ito.list-page title="Inventarios" subtitle="Escanear código → ver ficha → actualizar estado o sede">
    <x-slot:actions>
        <a href="{{ route('inventarios.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nuevo ítem</a>
    </x-slot:actions>

    <x-slot:toolbar>
        <div class="w-100 d-flex flex-column gap-2">
            <form method="GET" action="{{ route('inventarios.index') }}" class="ito-toolbar-filters d-flex flex-wrap align-items-end gap-2">
                <div class="ito-field ito-field-grow" style="min-width: 200px; max-width: 320px;">
                    <label for="inv-codigo">Escanear / ir a código</label>
                    <div class="input-group">
                        <input
                            type="search"
                            id="inv-codigo"
                            name="codigo"
                            class="form-control"
                            placeholder="CHL-0042"
                            autocomplete="off"
                            autofocus
                        >
                        <button type="submit" class="btn btn-primary">Abrir ficha</button>
                    </div>
                </div>
                <p class="small text-muted mb-0 align-self-center">Lector de códigos: enfocá el campo y escaneá.</p>
            </form>
            <form method="GET" action="{{ route('inventarios.index') }}" class="ito-toolbar-filters d-flex flex-wrap align-items-end justify-content-between gap-2">
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
                    <div class="ito-field">
                        <label>Estado</label>
                        <select name="estado" class="form-select">
                            <option value="">Todos</option>
                            @foreach(\App\Models\InventarioItem::ESTADOS as $k => $label)
                                <option value="{{ $k }}" @selected(request('estado') == $k)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-secondary btn-sm">Filtrar</button>
                </div>
                <div class="ito-search">
                    <i class="bi bi-search"></i>
                    <input type="text" name="q" class="form-control" value="{{ request('q') }}" placeholder="Nombre, código, marca…">
                </div>
            </form>
        </div>
    </x-slot:toolbar>

    <x-slot:filters>
        <x-ito.filter-chips
            :clear-url="route('inventarios.index')"
            :filters="[
                'sede_id' => 'Sede',
                'tipo' => 'Tipo',
                'propietario_tipo' => 'Propiedad',
                'estado' => 'Estado',
                'q' => 'Búsqueda',
            ]"
        />
    </x-slot:filters>

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
                    <td>
                        {{ $item->marca ?? '—' }}
                        @if($item->modelo)
                            / {{ $item->modelo }}
                        @endif
                    </td>
                    <td>
                        <x-ito.status :tone="$estadoTone" :label="$estadoLabel" />
                    </td>
                    <td>
                        <x-ito.actions :id="'inv-'.$item->id">
                            <li><a class="dropdown-item" href="{{ route('inventarios.show', $item) }}"><i class="bi bi-eye"></i> Ver</a></li>
                            <li><a class="dropdown-item" href="{{ route('inventarios.edit', $item) }}"><i class="bi bi-pencil"></i> Editar</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('inventarios.destroy', $item) }}" method="POST" data-confirm="¿Eliminar ítem?">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash"></i> Eliminar</button>
                                </form>
                            </li>
                        </x-ito.actions>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">
                        <x-ito.empty
                            title="{{ request()->hasAny(['sede_id','tipo','propietario_tipo','estado','q']) ? 'Ningún ítem coincide' : 'Inventario vacío' }}"
                            description="{{ request()->hasAny(['sede_id','tipo','propietario_tipo','estado','q']) ? 'Limpiá filtros o buscá por código arriba.' : 'Cargá el primer instrumento o usá el campo de código cuando ya exista.' }}"
                            icon="bi-box-seam"
                            :action-href="request()->hasAny(['sede_id','tipo','propietario_tipo','estado','q']) ? route('inventarios.index') : route('inventarios.create')"
                            :action-label="request()->hasAny(['sede_id','tipo','propietario_tipo','estado','q']) ? 'Limpiar filtros' : 'Nuevo ítem'"
                        />
                    </td>
                </tr>
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
