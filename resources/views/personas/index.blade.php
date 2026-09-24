@extends('layouts.app')

@section('title', 'Personas')
@section('page-title', 'Personas')

@section('content')
<x-ito.list-page title="Personas" subtitle="Una ficha por persona, con todas sus funciones: alumno, docente, coordinación, cuenta de acceso.">
    <x-slot:actions>
        @can('personas.create')
            <a href="{{ route('personas.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nueva persona</a>
        @endcan
    </x-slot:actions>

    <x-slot:toolbar>
        <form method="GET" class="d-flex flex-wrap gap-2 w-100" role="search">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="max-width: 320px" placeholder="Nombre, DNI, email o teléfono" aria-label="Buscar persona">
            <select name="funcion" class="form-select form-select-sm" style="max-width: 200px" aria-label="Filtrar por función">
                <option value="">Todas</option>
                <option value="alumno" @selected(request('funcion') === 'alumno')>Alumnos</option>
                <option value="profesor" @selected(request('funcion') === 'profesor')>Docentes</option>
                <option value="con_cuenta" @selected(request('funcion') === 'con_cuenta')>Con cuenta</option>
                <option value="sin_cuenta" @selected(request('funcion') === 'sin_cuenta')>Sin cuenta</option>
            </select>
            <button class="btn btn-outline-secondary btn-sm">Buscar</button>
        </form>
    </x-slot:toolbar>

    <table class="ito-table">
        <thead>
            <tr>
                <th>Persona</th>
                <th>DNI</th>
                <th>Funciones</th>
                <th>Cuenta</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($personas as $p)
                <tr>
                    <td class="fw-semibold"><a href="{{ route('personas.show', $p) }}">{{ $p->nombre_completo }}</a>
                        @if($p->telefono)<div class="small text-muted">{{ $p->telefono }}</div>@endif
                    </td>
                    <td class="ito-mono">{{ $p->dni ?? '—' }}</td>
                    <td>
                        @if($p->alumnos->isNotEmpty())<span class="badge text-bg-light border">Alumno</span>@endif
                        @if($p->profesor)<span class="badge text-bg-light border">Docente</span>@endif
                        @if($p->alumnos->isEmpty() && ! $p->profesor)<span class="text-muted small">—</span>@endif
                    </td>
                    <td>
                        @if($p->user)
                            <span class="ito-mono small">{{ $p->user->username }}</span>
                            @unless($p->user->activo)<span class="badge text-bg-secondary">inactiva</span>@endunless
                        @else
                            <span class="text-muted small">Sin cuenta</span>
                        @endif
                    </td>
                    <td><x-ito.status :tone="$p->estado === 'activo' ? 'success' : 'neutral'" :label="\App\Models\Persona::ESTADOS[$p->estado] ?? $p->estado" /></td>
                </tr>
            @empty
                <tr><td colspan="5" class="ito-empty">No hay personas que coincidan.</td></tr>
            @endforelse
        </tbody>
    </table>

    <x-slot:footer>
        {{ $personas->links() }}
    </x-slot:footer>
</x-ito.list-page>
@endsection
