@extends('layouts.app')

@section('title', 'Preinscripciones')
@section('page-title', 'Preinscripciones')

@section('content')
@if(session('success'))
    <div class="alert alert-success" role="status">{{ session('success') }}</div>
@endif
<nav class="mb-3 d-flex gap-2" aria-label="Estados">
    @foreach(['pendiente' => 'En lista de espera', 'aprobada' => 'Aprobadas', 'rechazada' => 'Rechazadas'] as $clave => $etiqueta)
        <a href="{{ route('preinscripciones.index', ['estado' => $clave]) }}" class="btn btn-sm {{ $estado === $clave ? 'btn-primary' : 'btn-outline-primary' }}">{{ $etiqueta }}</a>
    @endforeach
</nav>
<div class="table-responsive">
    <table class="table align-middle">
        <thead><tr><th>Nombre</th><th>Sede</th><th>Bloque</th><th>Contacto</th><th>Recibida</th><th></th></tr></thead>
        <tbody>
        @forelse($preinscripciones as $p)
            <tr>
                <td>{{ $p->nombre }} {{ $p->apellido }}</td>
                <td>{{ $p->sede?->nombre }}</td>
                <td>{{ $p->bloque?->nombre ?? 'Sin preferencia' }}</td>
                <td>{{ $p->telefono }}</td>
                <td>{{ $p->created_at->format('d/m/Y H:i') }}</td>
                <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('preinscripciones.show', $p) }}">Ver</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-muted">No hay preinscripciones en esta lista.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $preinscripciones->links() }}
@endsection
