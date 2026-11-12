@extends('layouts.app')

@section('title', 'Gastos')
@section('page-title', 'Gastos')

@section('content')
<x-ito.list-page title="Gastos" subtitle="Registro de gastos operativos">
    <x-slot:actions>
        <a href="{{ route('gastos.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nuevo gasto</a>
        @if(request()->boolean('papelera'))
            <a href="{{ route('gastos.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver a los gastos</a>
        @else
            <a href="{{ route('gastos.index', ['papelera' => 1]) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-trash"></i> Papelera</a>
        @endif
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
                <label>Tipo</label>
                <select name="tipo" class="form-select">
                    <option value="">Todos</option>
                    @foreach(\App\Models\Gasto::TIPOS as $k => $v)
                        <option value="{{ $k }}" @selected(request('tipo') === $k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ito-field">
                <label>Desde</label>
                <input type="date" name="desde" class="form-control" value="{{ request('desde') }}">
            </div>
            <div class="ito-field">
                <label>Hasta</label>
                <input type="date" name="hasta" class="form-control" value="{{ request('hasta') }}">
            </div>
            @if(request()->boolean('papelera'))<input type="hidden" name="papelera" value="1">@endif
            <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
        </form>
    </x-slot:toolbar>

    <x-slot:filters>
        <x-ito.filter-chips
            :clear-url="route('gastos.index')"
            :filters="[
                'sede_id' => 'Sede',
                'tipo' => 'Tipo',
                'desde' => 'Desde',
                'hasta' => 'Hasta',
            ]"
        />
    </x-slot:filters>

    <table class="ito-table">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Subtipo</th>
                <th>Descripción</th>
                <th>Sede / Bloque</th>
                <th class="text-end">Monto</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($gastos as $g)
                <tr>
                    <td class="ito-mono">{{ $g->fecha->format('d/m/Y') }}</td>
                    <td>{{ \App\Models\Gasto::TIPOS[$g->tipo] ?? $g->tipo }}</td>
                    <td>{{ $g->subtipo ?: '—' }}</td>
                    <td>{{ Str::limit($g->descripcion, 40) ?: '—' }}</td>
                    <td>
                        @if($g->sede || $g->bloque)
                            <x-ito.person
                                :name="$g->sede?->nombre ?? '—'"
                                :sub="$g->bloque?->nombre"
                            />
                        @else
                            —
                        @endif
                    </td>
                    <td class="ito-mono text-end">$ {{ number_format($g->monto, 2, ',', '.') }}</td>
                    <td>
                        @if($g->trashed())
                            <small class="d-block text-muted">Borrado {{ $g->deleted_at?->format('d/m/Y') }}{{ $g->eliminadoPor ? ' por '.$g->eliminadoPor->name : '' }}{{ $g->motivo_eliminacion ? ': '.$g->motivo_eliminacion : '' }}</small>
                            @can('delete', $g)
                                <form action="{{ route('gastos.restaurar', $g->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-counterclockwise"></i> Restaurar</button>
                                </form>
                            @endcan
                        @else
                        <x-ito.actions :id="'gasto-'.$g->id">
                            <li><a class="dropdown-item" href="{{ route('gastos.show', $g) }}"><i class="bi bi-eye"></i> Ver</a></li>
                            <li><a class="dropdown-item" href="{{ route('gastos.edit', $g) }}"><i class="bi bi-pencil"></i> Editar</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('gastos.destroy', $g) }}" method="POST" data-confirm="¿Mandar este gasto a la papelera?" class="px-3 py-1">
                                    @csrf @method('DELETE')
                                    <input type="text" name="motivo" class="form-control form-control-sm mb-1" maxlength="500" placeholder="Motivo (opcional)">
                                    <button type="submit" class="btn btn-sm btn-outline-danger w-100"><i class="bi bi-trash"></i> Eliminar</button>
                                </form>
                            </li>
                        </x-ito.actions>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">
                        <x-ito.empty
                            title="{{ request()->hasAny(['sede_id','tipo','desde','hasta']) ? 'Sin gastos con estos filtros' : 'No hay gastos' }}"
                            description="{{ request()->hasAny(['sede_id','tipo','desde','hasta']) ? 'Limpiá filtros o cambiá el período.' : 'Registrá egresos operativos para seguir el mes.' }}"
                            icon="bi-wallet2"
                            :action-href="request()->hasAny(['sede_id','tipo','desde','hasta']) ? route('gastos.index') : route('gastos.create')"
                            :action-label="request()->hasAny(['sede_id','tipo','desde','hasta']) ? 'Limpiar filtros' : 'Nuevo gasto'"
                        />
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <x-slot:footer>
        <div class="ito-footer-meta">@if(method_exists($gastos, 'total'))
            {{ $gastos->total() }} registros
        @endif
        </div>
        {{ $gastos->withQueryString()->links('pagination::bootstrap-5') }}
    </x-slot:footer>
</x-ito.list-page>
@endsection
