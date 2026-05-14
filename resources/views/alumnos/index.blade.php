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
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">@if($isAdmin) Listado de Alumnos @else Mis alumnos @endif</h5>
        @if($isAdmin)
        <div>
            <a href="{{ route('alumnos.export') }}" class="btn btn-success btn-sm">
                <i class="bi bi-file-earmark-excel"></i> Exportar Excel
            </a>
            <a href="{{ route('alumnos.import.form') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-upload"></i> Importar CSV/Excel
            </a>
            <a href="{{ route('alumnos.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle"></i> Nuevo Alumno
            </a>
        </div>
        @endif
    </div>
    <div class="card-body">
        <form method="GET" class="mb-3">
            <div class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control" placeholder="Buscar..." value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <select name="sede_id" class="form-select">
                        <option value="">Todas las sedes</option>
                        @foreach($sedes as $sede)
                        <option value="{{ $sede->id }}" {{ request('sede_id') == $sede->id ? 'selected' : '' }}>{{ $sede->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="bloque_id" class="form-select">
                        <option value="">Todos los bloques</option>
                        @foreach($bloques as $bloque)
                        <option value="{{ $bloque->id }}" {{ request('bloque_id') == $bloque->id ? 'selected' : '' }}>{{ $bloque->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="tipo_tambor" class="form-select">
                        <option value="">Todos los tipos de tambor</option>
                        @foreach($tiposTambor as $tipo)
                        <option value="{{ $tipo }}" {{ request('tipo_tambor') == $tipo ? 'selected' : '' }}>{{ $tipo }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="tambor_procedencia" class="form-select">
                        <option value="">Todas las procedencias</option>
                        @foreach($procedenciasTambor as $procedencia)
                        <option value="{{ $procedencia }}" {{ request('tambor_procedencia') == $procedencia ? 'selected' : '' }}>{{ $procedencia }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                </div>
            </div>
        </form>

        <div class="table-responsive alumnos-table">
            <table class="table table-striped table-alumnos">
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
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($alumnos as $alumno)
                    <tr>
                        <td class="col-nombre">{{ $alumno->nombre_apellido }}</td>
                        <td>{{ $alumno->dni }}</td>
                        <td>{{ $alumno->edad }} años</td>
                        <td>{{ $alumno->instrumento_principal }}</td>
                        <td>{{ $alumno->tipo_tambor ?? '—' }}</td>
                        <td>{{ $alumno->tambor_procedencia ?? '—' }}</td>
                        <td>{{ $alumno->bloques->isNotEmpty() ? $alumno->bloques->pluck('nombre')->join(', ') : ($alumno->bloque ? $alumno->bloque->nombre : '-') }}</td>
                        <td>{{ $alumno->sede->nombre }}</td>
                        <td>
                            <a href="{{ $isAdmin ? route('alumnos.show', $alumno) : route('profesor.alumnos.show', $alumno) }}" class="btn btn-sm btn-info">
                                <i class="bi bi-eye"></i>
                            </a>
                            @if($isAdmin)
                            <a href="{{ route('alumnos.edit', $alumno) }}" class="btn btn-sm btn-warning">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route('alumnos.destroy', $alumno) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center">No hay alumnos registrados</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $alumnos->withQueryString()->onEachSide(1)->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection

