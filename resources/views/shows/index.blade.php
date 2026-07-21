@extends('layouts.app')

@section('title', 'Próximos shows')
@section('page-title', 'Próximos shows')

@section('content')
<x-ito.list-page title="Shows" subtitle="Presentaciones y convocatorias">
    <x-slot:actions>
        <a href="{{ route('shows.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nuevo show</a>
    </x-slot:actions>

    <x-slot:toolbar>
        <form method="GET" class="ito-toolbar-filters w-100 d-flex flex-wrap align-items-center gap-3">
            <div class="form-check m-0">
                <input type="checkbox" name="proximos" value="1" class="form-check-input" id="proximos" @checked(request('proximos'))>
                <label class="form-check-label" for="proximos">Solo próximos</label>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
        </form>
    </x-slot:toolbar>

    <table class="ito-table">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Título</th>
                <th>Lugar</th>
                <th>Participación</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($shows as $s)
                <tr>
                    <td class="ito-mono">
                        {{ $s->fecha->format('d/m/Y') }}
                        @if($s->hora_inicio) {{ \Carbon\Carbon::parse($s->hora_inicio)->format('H:i') }} @endif
                    </td>
                    <td class="fw-semibold">{{ $s->titulo }}</td>
                    <td>{{ $s->lugar ?? '—' }}</td>
                    <td>
                        @if($s->convocatoria_abierta)
                            <x-ito.status tone="info" label="Convocatoria abierta" />
                        @else
                            {{ $s->bloques->pluck('nombre')->join(', ') ?: '—' }}
                        @endif
                    </td>
                    <td>
                        <x-ito.actions :id="'show-'.$s->id">
                            <li><a class="dropdown-item" href="{{ route('shows.show', $s) }}"><i class="bi bi-eye"></i> Ver</a></li>
                            <li><a class="dropdown-item" href="{{ route('shows.edit', $s) }}"><i class="bi bi-pencil"></i> Editar</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('shows.destroy', $s) }}" method="POST" onsubmit="return confirm('¿Eliminar show?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash"></i> Eliminar</button>
                                </form>
                            </li>
                        </x-ito.actions>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="ito-empty">No hay shows. <a href="{{ route('shows.create') }}">Crear uno</a></td></tr>
            @endforelse
        </tbody>
    </table>

    <x-slot:footer>
        <div class="ito-footer-meta">@if(method_exists($shows, 'total'))
            {{ $shows->total() }} registros
        @endif
        </div>
        {{ $shows->withQueryString()->links('pagination::bootstrap-5') }}
    </x-slot:footer>
</x-ito.list-page>
@endsection
