@extends('layouts.app')

@section('title', 'Inventarios')
@section('page-title', 'Inventarios por sede')

@section('content')
<div class="card">
    <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>Inventarios</div>
        <a href="{{ route('inventarios.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle"></i> Nuevo ítem</a>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-3">
                <label class="form-label small">Sede</label>
                <select name="sede_id" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    @foreach($sedes as $s)
                    <option value="{{ $s->id }}" {{ request('sede_id') == $s->id ? 'selected' : '' }}>{{ $s->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Tipo</label>
                <select name="tipo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($tipos as $k => $label)
                    <option value="{{ $k }}" {{ request('tipo') == $k ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Propiedad</label>
                <select name="propietario_tipo" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    @foreach($propietarios as $k => $label)
                    <option value="{{ $k }}" {{ request('propietario_tipo') == $k ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Buscar</label>
                <input type="text" name="q" class="form-control form-control-sm" value="{{ request('q') }}" placeholder="Nombre, código, marca, modelo…">
            </div>
            <div class="col-md-1 d-grid">
                <button class="btn btn-sm btn-outline-primary" type="submit">Filtrar</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Sede</th>
                        <th>Tipo</th>
                        <th>Nombre</th>
                        <th>Propiedad</th>
                        <th>Cant.</th>
                        <th>Marca/Modelo</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                    <tr>
                        <td>{{ $item->sede?->nombre }}</td>
                        <td><span class="badge bg-secondary">{{ $item->tipo_label }}</span></td>
                        <td>
                            <strong>{{ $item->nombre }}</strong>
                            @if($item->codigo)<div class="text-muted small">Código: {{ $item->codigo }}</div>@endif
                            @if($item->utilitario)<span class="badge bg-warning text-dark">Utilitario</span>@endif
                        </td>
                        <td>
                            {{ $item->propietario_label }}
                            @if($item->propietario_tipo === 'alumno' && $item->alumno)
                                <div class="text-muted small">{{ $item->alumno->nombre_apellido }}</div>
                            @endif
                        </td>
                        <td>
                            @if($item->es_consumible)
                                {{ number_format((float)$item->cantidad, 2, ',', '.') }} {{ $item->unidad ?? '' }}
                            @else
                                1 u
                            @endif
                        </td>
                        <td>{{ $item->marca ?? '—' }} @if($item->modelo) / {{ $item->modelo }} @endif</td>
                        <td>{{ \\App\\Models\\InventarioItem::ESTADOS[$item->estado] ?? $item->estado }}</td>
                        <td class="text-end">
                            <a href="{{ route('inventarios.show', $item) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('inventarios.edit', $item) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                            <form action="{{ route('inventarios.destroy', $item) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar ítem?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center text-muted">No hay ítems cargados. <a href="{{ route('inventarios.create') }}">Crear uno</a></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $items->withQueryString()->links() }}
    </div>
</div>
@endsection

