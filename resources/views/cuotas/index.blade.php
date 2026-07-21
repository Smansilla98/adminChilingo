@extends('layouts.app')

@section('title', 'Cuotas')
@section('page-title', 'Cuotas')

@section('content')
@php $hasAlcance = \Illuminate\Support\Facades\Schema::hasColumn('cuotas', 'alcance'); @endphp

<x-ito.list-page title="Cuotas" subtitle="Definición de cuotas por período">
    <x-slot:actions>
        <a href="{{ route('cuotas.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nueva cuota</a>
    </x-slot:actions>

    <x-slot:toolbar>
        <form method="GET" class="ito-toolbar-filters w-100 d-flex flex-wrap align-items-end gap-2">
            <div class="ito-field">
                <label>Año</label>
                <input type="number" name="año" class="form-control" placeholder="Año" value="{{ request('año') }}" min="2020" max="2030">
            </div>
            @if($hasAlcance)
                <div class="ito-field">
                    <label>Para quién</label>
                    <select name="alcance" class="form-select">
                        <option value="">Todos</option>
                        <option value="general" @selected(request('alcance') === 'general')>General</option>
                        <option value="sede" @selected(request('alcance') === 'sede')>Por sede</option>
                        <option value="bloque" @selected(request('alcance') === 'bloque')>Por bloque</option>
                    </select>
                </div>
                <div class="ito-field">
                    <label>Sede (cuota)</label>
                    <select name="sede_id" class="form-select">
                        <option value="">Todas</option>
                        @foreach($sedes ?? [] as $s)
                            <option value="{{ $s->id }}" @selected((string) request('sede_id') === (string) $s->id)>{{ $s->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="ito-field">
                <label>Bloque</label>
                <select name="bloque_id" class="form-select">
                    <option value="">Todos</option>
                    @foreach($bloques ?? [] as $b)
                        <option value="{{ $b->id }}" @selected(request('bloque_id') == $b->id)>{{ $b->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
        </form>
    </x-slot:toolbar>

    <table class="ito-table">
        <thead>
            <tr>
                <th>Nombre</th>
                @if($hasAlcance)
                    <th>Para quién</th>
                    <th>Sede</th>
                @endif
                <th>Bloque</th>
                <th>Año</th>
                <th>Mes</th>
                <th>Monto</th>
                <th>Activo</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($cuotas as $c)
                <tr>
                    <td class="fw-semibold">{{ $c->nombre }}</td>
                    @if($hasAlcance)
                        <td>
                            @if(($c->alcance ?? 'bloque') === \App\Models\Cuota::ALCANCE_GENERAL)
                                General
                            @elseif(($c->alcance ?? 'bloque') === \App\Models\Cuota::ALCANCE_SEDE)
                                Sede
                            @else
                                Bloque
                            @endif
                        </td>
                        <td>{{ $c->sede?->nombre ?? ($c->bloque?->sede?->nombre ?? '—') }}</td>
                    @endif
                    <td>{{ $c->bloque?->nombre ?? '—' }}</td>
                    <td class="ito-mono">{{ $c->año }}</td>
                    <td>{{ $c->nombre_mes ?? '—' }}</td>
                    <td class="ito-mono">$ {{ number_format($c->monto, 2, ',', '.') }}</td>
                    <td>
                        <x-ito.status :tone="$c->activo ? 'success' : 'neutral'" :label="$c->activo ? 'Sí' : 'No'" />
                    </td>
                    <td>
                        <x-ito.actions :id="'cuota-'.$c->id">
                            <li><a class="dropdown-item" href="{{ route('cuotas.show', $c) }}"><i class="bi bi-eye"></i> Ver</a></li>
                            <li><a class="dropdown-item" href="{{ route('cuotas.edit', $c) }}"><i class="bi bi-pencil"></i> Editar</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('cuotas.destroy', $c) }}" method="POST" onsubmit="return confirm('¿Eliminar cuota?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash"></i> Eliminar</button>
                                </form>
                            </li>
                        </x-ito.actions>
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ $hasAlcance ? 9 : 7 }}" class="ito-empty">No hay cuotas. <a href="{{ route('cuotas.create') }}">Crear una</a></td></tr>
            @endforelse
        </tbody>
    </table>

    <x-slot:footer>
        <div class="ito-footer-meta">@if(method_exists($cuotas, 'total'))
            {{ $cuotas->total() }} registros
        @endif
        </div>
        {{ $cuotas->withQueryString()->links('pagination::bootstrap-5') }}
    </x-slot:footer>
</x-ito.list-page>
@endsection
