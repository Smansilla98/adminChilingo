@extends('layouts.app')

@section('title', 'Eventos')
@section('page-title', 'Eventos')

@section('content')
<x-ito.list-page title="Eventos" subtitle="Clases especiales, ensayos y actividades">
    <x-slot:actions>
        <a href="{{ route('eventos.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nuevo evento</a>
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
                <label>Profesor</label>
                <select name="profesor_id" class="form-select">
                    <option value="">Todos</option>
                    @foreach($profesores as $p)
                        <option value="{{ $p->id }}" @selected(request('profesor_id') == $p->id)>{{ $p->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ito-field">
                <label>Tipo</label>
                <select name="tipo_evento" class="form-select">
                    <option value="">Todos</option>
                    @foreach($tiposEvento as $t)
                        <option value="{{ $t }}" @selected(request('tipo_evento') === $t)>{{ ucfirst(str_replace('_', ' ', $t)) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
        </form>
    </x-slot:toolbar>

    <table class="ito-table">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Título</th>
                <th>Tipo</th>
                <th>Sede</th>
                <th>Profesor / Bloque</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($eventos as $evento)
                <tr>
                    <td class="ito-mono">
                        {{ $evento->fecha->format('d/m/Y') }}
                        @if($evento->hora_inicio) {{ $evento->hora_inicio->format('H:i') }} @endif
                    </td>
                    <td class="fw-semibold">{{ $evento->titulo }}</td>
                    <td>
                        <x-ito.status tone="info" :label="ucfirst(str_replace('_', ' ', $evento->tipo_evento))" />
                    </td>
                    <td>{{ $evento->sede?->nombre ?? '—' }}</td>
                    <td>{{ $evento->profesor?->nombre ?? ($evento->bloque?->nombre ?? '—') }}</td>
                    <td>
                        <x-ito.actions :id="'evento-'.$evento->id">
                            <li><a class="dropdown-item" href="{{ route('eventos.show', $evento) }}"><i class="bi bi-eye"></i> Ver</a></li>
                            <li><a class="dropdown-item" href="{{ route('eventos.edit', $evento) }}"><i class="bi bi-pencil"></i> Editar</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('eventos.destroy', $evento) }}" method="POST" data-confirm="¿Eliminar este evento?">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash"></i> Eliminar</button>
                                </form>
                            </li>
                        </x-ito.actions>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="ito-empty">No hay eventos. <a href="{{ route('eventos.create') }}">Crear el primero</a>.</td></tr>
            @endforelse
        </tbody>
    </table>

    <x-slot:footer>
        <div class="ito-footer-meta">@if(method_exists($eventos, 'total'))
            {{ $eventos->total() }} registros
        @endif
        </div>
        {{ $eventos->withQueryString()->links('pagination::bootstrap-5') }}
    </x-slot:footer>
</x-ito.list-page>
@endsection
