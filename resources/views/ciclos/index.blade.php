@extends('layouts.app')

@section('title', 'Ciclos lectivos')
@section('page-title', 'Ciclos lectivos')

@section('content')
@if(session('success'))
    <div class="alert alert-success" role="status">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

@can('alumnos.update')
<div class="ito-card p-4 mb-4" style="max-width: 760px">
    <h2 class="h6">Abrir un ciclo nuevo</h2>
    <p class="text-muted small">El ciclo va de marzo a febrero para las cuotas. Crea una reinscripción pendiente para cada alumno activo y les avisa. No cambia cuotas, pagos ni asistencias.</p>
    <form method="POST" action="{{ route('ciclos.store') }}" class="row g-2 align-items-end">
        @csrf
        <div class="col-sm-2"><label class="form-label small" for="anio">Año de inicio</label><input id="anio" type="number" name="anio" class="form-control" value="{{ old('anio', $anioSugerido) }}" min="2000" max="2100" required></div>
        <div class="col-sm-6"><label class="form-label small" for="nombre">Nombre (opcional)</label><input id="nombre" name="nombre" class="form-control" value="{{ old('nombre') }}" maxlength="80"></div>
        <div class="col-12"><button class="btn btn-primary">Abrir ciclo y avisar</button></div>
    </form>
</div>
@endcan

<div class="table-responsive">
    <table class="table align-middle">
        <thead><tr><th>Año</th><th>Nombre</th><th>Cuotas</th><th></th></tr></thead>
        <tbody>
        @forelse($ciclos as $c)
            <tr>
                <td>{{ $c->anio }}</td>
                <td>{{ $c->nombre }}</td>
                <td>{{ $c->inicio->format('m/Y') }} a {{ $c->fin->format('m/Y') }}</td>
                <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('ciclos.show', $c) }}">Reinscripciones</a></td>
            </tr>
        @empty
            <tr><td colspan="4" class="text-muted">Todavía no hay ciclos abiertos.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
