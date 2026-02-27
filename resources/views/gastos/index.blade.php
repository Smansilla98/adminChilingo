@extends('layouts.app')

@section('title', 'Gastos')
@section('page-title', 'Gastos')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Registro de gastos</h5>
        <a href="{{ route('gastos.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle"></i> Nuevo gasto</a>
    </div>
    <div class="card-body">
        <form method="GET" class="mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small">Sede</label>
                    <select name="sede_id" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        @foreach($sedes as $s)
                        <option value="{{ $s->id }}" {{ request('sede_id') == $s->id ? 'selected' : '' }}>{{ $s->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Tipo</label>
                    <select name="tipo" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        @foreach(\App\Models\Gasto::TIPOS as $k => $v)
                        <option value="{{ $k }}" {{ request('tipo') === $k ? 'selected' : '' }}>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Desde</label>
                    <input type="date" name="desde" class="form-control form-control-sm" value="{{ request('desde') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Hasta</label>
                    <input type="date" name="hasta" class="form-control form-control-sm" value="{{ request('hasta') }}">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
                </div>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Subtipo</th>
                        <th>Descripción</th>
                        <th>Sede / Bloque</th>
                        <th class="text-end">Monto</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($gastos as $g)
                    <tr>
                        <td>{{ $g->fecha->format('d/m/Y') }}</td>
                        <td>{{ \App\Models\Gasto::TIPOS[$g->tipo] ?? $g->tipo }}</td>
                        <td>{{ $g->subtipo ?: '-' }}</td>
                        <td>{{ Str::limit($g->descripcion, 40) ?: '-' }}</td>
                        <td>
                            @if($g->sede){{ $g->sede->nombre }}@endif
                            @if($g->bloque)<br><small class="text-muted">{{ $g->bloque->nombre }}</small>@endif
                            @if(!$g->sede && !$g->bloque)<span class="text-muted">-</span>@endif
                        </td>
                        <td class="text-end">$ {{ number_format($g->monto, 2, ',', '.') }}</td>
                        <td>
                            <a href="{{ route('gastos.show', $g) }}" class="btn btn-sm btn-info" title="Ver"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('gastos.edit', $g) }}" class="btn btn-sm btn-warning" title="Editar"><i class="bi bi-pencil"></i></a>
                            <form action="{{ route('gastos.destroy', $g) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este gasto?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-muted">No hay gastos registrados. <a href="{{ route('gastos.create') }}">Registrar el primero</a>.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $gastos->withQueryString()->links() }}
    </div>
</div>
@endsection
