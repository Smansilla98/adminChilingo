@extends('layouts.app')

@section('title', 'Eventos')
@section('page-title', 'Eventos')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Eventos</h5>
        <a href="{{ route('eventos.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle"></i> Nuevo evento</a>
    </div>
    <div class="card-body">
        <form method="GET" class="mb-3">
            <div class="row g-2 align-items-end">
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
                    <label class="form-label small">Profesor</label>
                    <select name="profesor_id" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        @foreach($profesores as $p)
                        <option value="{{ $p->id }}" {{ request('profesor_id') == $p->id ? 'selected' : '' }}>{{ $p->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Tipo</label>
                    <select name="tipo_evento" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        @foreach($tiposEvento as $t)
                        <option value="{{ $t }}" {{ request('tipo_evento') === $t ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $t)) }}</option>
                        @endforeach
                    </select>
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
                        <th>Título</th>
                        <th>Tipo</th>
                        <th>Sede</th>
                        <th>Profesor / Bloque</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($eventos as $evento)
                    <tr>
                        <td>{{ $evento->fecha->format('d/m/Y') }} @if($evento->hora_inicio) {{ $evento->hora_inicio->format('H:i') }} @endif</td>
                        <td>{{ $evento->titulo }}</td>
                        <td><span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $evento->tipo_evento)) }}</span></td>
                        <td>{{ $evento->sede?->nombre ?? '—' }}</td>
                        <td>{{ $evento->profesor?->nombre ?? ($evento->bloque?->nombre ?? '—') }}</td>
                        <td>
                            <a href="{{ route('eventos.show', $evento) }}" class="btn btn-sm btn-info" title="Ver"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('eventos.edit', $evento) }}" class="btn btn-sm btn-warning" title="Editar"><i class="bi bi-pencil"></i></a>
                            <form action="{{ route('eventos.destroy', $evento) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este evento?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted">No hay eventos. <a href="{{ route('eventos.create') }}">Crear el primero</a>.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $eventos->withQueryString()->links() }}
    </div>
</div>
@endsection
