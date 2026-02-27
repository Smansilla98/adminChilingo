@extends('layouts.app')

@section('title', 'Próximos shows')
@section('page-title', 'Próximos shows')

@section('content')
<div class="card shadow-sm">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Shows</h5>
        <a href="{{ route('shows.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle"></i> Nuevo show</a>
    </div>
    <div class="card-body">
        <form method="GET" class="mb-3">
            <div class="form-check form-check-inline">
                <input type="checkbox" name="proximos" value="1" class="form-check-input" id="proximos" {{ request('proximos') ? 'checked' : '' }}>
                <label class="form-check-label" for="proximos">Solo próximos</label>
            </div>
            <button type="submit" class="btn btn-sm btn-outline-primary">Filtrar</button>
        </form>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Título</th>
                        <th>Lugar</th>
                        <th>Participación</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($shows as $s)
                    <tr>
                        <td>{{ $s->fecha->format('d/m/Y') }} @if($s->hora_inicio) {{ \Carbon\Carbon::parse($s->hora_inicio)->format('H:i') }} @endif</td>
                        <td>{{ $s->titulo }}</td>
                        <td>{{ $s->lugar ?? '—' }}</td>
                        <td>
                            @if($s->convocatoria_abierta)
                                <span class="badge bg-info">Convocatoria abierta</span>
                            @else
                                {{ $s->bloques->pluck('nombre')->join(', ') ?: '—' }}
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('shows.show', $s) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('shows.edit', $s) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                            <form action="{{ route('shows.destroy', $s) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar show?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center text-muted">No hay shows. <a href="{{ route('shows.create') }}">Crear uno</a></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $shows->withQueryString()->links() }}
    </div>
</div>
@endsection
