@extends('layouts.app')

@section('title', $ciclo->nombre)
@section('page-title', $ciclo->nombre.' · reinscripciones')

@section('content')
@if(session('success'))
    <div class="alert alert-success" role="status">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<p class="mb-3">
    <strong>{{ $resumen['confirmadas'] }}</strong> reinscriptos ·
    <strong>{{ $resumen['pendientes'] }}</strong> pendientes ·
    <strong>{{ $resumen['bajas'] }}</strong> dados de baja
</p>
<nav class="mb-3 d-flex gap-2" aria-label="Filtrar por estado">
    <a href="{{ route('ciclos.show', $ciclo) }}" class="btn btn-sm {{ $estado === null ? 'btn-primary' : 'btn-outline-primary' }}">Todos</a>
    <a href="{{ route('ciclos.show', [$ciclo, 'estado' => 'confirmada']) }}" class="btn btn-sm {{ $estado === 'confirmada' ? 'btn-primary' : 'btn-outline-primary' }}">Reinscriptos</a>
    <a href="{{ route('ciclos.show', [$ciclo, 'estado' => 'pendiente']) }}" class="btn btn-sm {{ $estado === 'pendiente' ? 'btn-primary' : 'btn-outline-primary' }}">Pendientes</a>
    <a href="{{ route('ciclos.show', [$ciclo, 'estado' => 'baja']) }}" class="btn btn-sm {{ $estado === 'baja' ? 'btn-primary' : 'btn-outline-primary' }}">Bajas</a>
</nav>
<div class="table-responsive">
    <table class="table align-middle">
        <thead><tr><th>Alumno</th><th>Estado</th><th>Confirmó</th><th></th></tr></thead>
        <tbody>
        @forelse($reinscripciones as $r)
            <tr>
                <td>{{ $r->alumno?->nombre_apellido }}</td>
                <td>{{ ['pendiente' => 'Pendiente', 'confirmada' => 'Reinscripto', 'baja' => 'Dado de baja'][$r->estado] ?? $r->estado }}</td>
                <td>{{ $r->confirmada_at?->format('d/m/Y H:i') ?? '—' }}</td>
                <td class="text-end">
                    @if($r->estado === \App\Models\Reinscripcion::PENDIENTE)
                        <form method="POST" action="{{ route('reinscripciones.baja', $r) }}" class="d-inline" data-confirm="¿Dar de baja a {{ $r->alumno?->nombre_apellido }}? Queda inactivo; sus cuotas y asistencias no se borran.">
                            @csrf
                            <button class="btn btn-sm btn-outline-danger">Dar de baja</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="text-muted">No hay alumnos en esta lista.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<a href="{{ route('ciclos.index') }}">Volver a ciclos</a>
@endsection
