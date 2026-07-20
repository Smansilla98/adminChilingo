@extends('layouts.app')

@section('title', 'Alumnos')
@section('page-title')
    @if(auth()->user()->isAdmin())
        Gestión de Alumnos
    @else
        Mis alumnos
    @endif
@endsection

@section('content')
@php $isAdmin = auth()->user()->isAdmin(); @endphp

<x-ito.list-page
    :title="$isAdmin ? 'Alumnos' : 'Mis alumnos'"
    :subtitle="$isAdmin ? 'Gestión completa del alumnado' : 'Alumnos de tus bloques'"
>
    <x-slot:actions>
        @if($isAdmin)
            <a href="{{ route('alumnos.export') }}" class="btn btn-secondary btn-sm"><i class="bi bi-file-earmark-excel"></i> Exportar</a>
            <a href="{{ route('alumnos.import.form') }}" class="btn btn-secondary btn-sm"><i class="bi bi-upload"></i> Importar</a>
            <a href="{{ route('alumnos.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nuevo alumno</a>
        @endif
    </x-slot:actions>

    <x-slot:toolbar>
        <form method="GET" class="ito-toolbar-filters w-100 d-flex flex-wrap align-items-end justify-content-between gap-2">
            <div class="d-flex flex-wrap gap-2 align-items-end flex-grow-1">
                <div class="ito-field">
                    <label>Sede</label>
                    <select name="sede_id" class="form-select">
                        <option value="">Todas</option>
                        @foreach($sedes as $sede)
                            <option value="{{ $sede->id }}" @selected(request('sede_id') == $sede->id)>{{ $sede->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ito-field">
                    <label>Bloque</label>
                    <select name="bloque_id" class="form-select">
                        <option value="">Todos</option>
                        @foreach($bloques as $bloque)
                            <option value="{{ $bloque->id }}" @selected(request('bloque_id') == $bloque->id)>{{ $bloque->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ito-field">
                    <label>Tipo tambor</label>
                    <select name="tipo_tambor" class="form-select">
                        <option value="">Todos</option>
                        @foreach($tiposTambor as $tipo)
                            <option value="{{ $tipo }}" @selected(request('tipo_tambor') == $tipo)>{{ $tipo }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ito-field">
                    <label>Procedencia</label>
                    <select name="tambor_procedencia" class="form-select">
                        <option value="">Todas</option>
                        @foreach($procedenciasTambor as $procedencia)
                            <option value="{{ $procedencia }}" @selected(request('tambor_procedencia') == $procedencia)>{{ $procedencia }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
            </div>
            <div class="ito-search">
                <i class="bi bi-search"></i>
                <input type="text" name="search" class="form-control" placeholder="Buscar alumno…" value="{{ request('search') }}">
            </div>
        </form>
    </x-slot:toolbar>

    <table class="ito-table alumnos-table table-alumnos">
        <thead>
            <tr>
                <th class="col-nombre">Nombre</th>
                <th>DNI</th>
                <th>Edad</th>
                <th>Instrumento</th>
                <th>Tipo tambor</th>
                <th>Procedencia</th>
                <th>Bloque</th>
                <th>Sede</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($alumnos as $alumno)
                <tr>
                    <td class="col-nombre">
                        <x-ito.person :name="$alumno->nombre_apellido" />
                    </td>
                    <td class="ito-mono">{{ $alumno->dni }}</td>
                    <td>{{ $alumno->edad }} años</td>
                    <td>{{ $alumno->instrumento_principal }}</td>
                    <td>{{ $alumno->tipo_tambor ?? '—' }}</td>
                    <td>{{ $alumno->tambor_procedencia ?? '—' }}</td>
                    <td>{{ $alumno->bloques->isNotEmpty() ? $alumno->bloques->pluck('nombre')->join(', ') : ($alumno->bloque?->nombre ?? '—') }}</td>
                    <td>{{ $alumno->sede->nombre }}</td>
                    <td>
                        <x-ito.actions :id="'alumno-'.$alumno->id">
                            <li>
                                <a class="dropdown-item" href="{{ $isAdmin ? route('alumnos.show', $alumno) : route('profesor.alumnos.show', $alumno) }}">
                                    <i class="bi bi-eye"></i> Ver
                                </a>
                            </li>
                            @if($isAdmin)
                                <li>
                                    <a class="dropdown-item" href="{{ route('alumnos.edit', $alumno) }}">
                                        <i class="bi bi-pencil"></i> Editar
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form action="{{ route('alumnos.destroy', $alumno) }}" method="POST" onsubmit="return confirm('¿Eliminar este alumno?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="dropdown-item text-danger">
                                            <i class="bi bi-trash"></i> Eliminar
                                        </button>
                                    </form>
                                </li>
                            @endif
                        </x-ito.actions>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="ito-empty">No hay alumnos registrados</td></tr>
            @endforelse
        </tbody>
    </table>

    <x-slot:footer>
        <div class="ito-footer-meta">
            @if(method_exists($alumnos, 'total'))
                {{ $alumnos->firstItem() ?? 0 }}–{{ $alumnos->lastItem() ?? 0 }} de {{ $alumnos->total() }}
            @endif
        </div>
        {{ $alumnos->withQueryString()->onEachSide(1)->links('pagination::bootstrap-5') }}
    </x-slot:footer>
</x-ito.list-page>
@endsection
