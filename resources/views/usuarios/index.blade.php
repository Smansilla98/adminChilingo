@extends('layouts.app')

@section('title', 'Usuarios y permisos')
@section('page-title', 'Usuarios y permisos')

@section('content')
<x-ito.list-page title="Usuarios y permisos" eyebrow="Administración" subtitle="Cada cuenta pertenece a una persona. Sus funciones y alcance definen qué puede hacer.">
    <x-slot:actions>
        @can('create', \App\Models\User::class)
            <a href="{{ route('usuarios.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nueva cuenta</a>
        @endcan
        @can('auditoria.view')
            <a href="{{ route('auditoria.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-journal-text"></i> Auditoría</a>
        @endcan
    </x-slot:actions>

    <x-slot:toolbar>
        <form method="GET" class="d-flex flex-wrap gap-2 w-100" role="search">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="max-width: 320px" placeholder="Nombre, usuario, email o DNI" aria-label="Buscar usuario">
            <select name="estado" class="form-select form-select-sm" style="max-width: 180px" aria-label="Estado">
                <option value="">Todas</option>
                <option value="activos" @selected(request('estado') === 'activos')>Activas</option>
                <option value="inactivos" @selected(request('estado') === 'inactivos')>Desactivadas</option>
            </select>
            <button class="btn btn-outline-secondary btn-sm">Buscar</button>
        </form>
    </x-slot:toolbar>

    <table class="ito-table">
        <thead>
            <tr><th>Persona</th><th>Usuario</th><th>Funciones</th><th>Última actividad</th><th>Estado</th></tr>
        </thead>
        <tbody>
            @forelse($usuarios as $usuario)
                @php($p = $usuario->persona)
                <tr>
                    <td class="fw-semibold"><a href="{{ route('usuarios.show', $usuario) }}">{{ $p?->nombre_completo ?? $usuario->name }}</a></td>
                    <td class="ito-mono small">{{ $usuario->username }}<div class="text-muted">{{ $usuario->email }}</div></td>
                    <td>
                        @if($p)
                            @foreach($p->asignaciones as $a)
                                @if($a->role)
                                    <span class="badge text-bg-light border">{{ \App\Domain\Acceso\CatalogoPermisos::nombreRol($a->role->name) }} — {{ $a->etiquetaAmbito() }}</span>
                                @endif
                            @endforeach
                            @if($p->profesor)<span class="badge text-bg-light border">Docente</span>@endif
                            @if($p->alumnos->isNotEmpty())<span class="badge text-bg-light border">Alumno</span>@endif
                        @endif
                        @if(in_array($usuario->role, ['admin', 'direccion'], true))<span class="badge text-bg-warning">Dirección (heredado)</span>@endif
                    </td>
                    <td class="small">{{ $usuario->ultimo_acceso_at?->diffForHumans() ?? '—' }}</td>
                    <td><x-ito.status :tone="$usuario->activo ? 'success' : 'neutral'" :label="$usuario->activo ? 'Activa' : 'Desactivada'" /></td>
                </tr>
            @empty
                <tr><td colspan="5" class="ito-empty">No hay cuentas que coincidan.</td></tr>
            @endforelse
        </tbody>
    </table>
    <x-slot:footer>{{ $usuarios->links() }}</x-slot:footer>
</x-ito.list-page>
@endsection
