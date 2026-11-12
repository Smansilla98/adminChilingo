@extends('layouts.app')

@section('title', 'Preinscripción de '.$p->nombre)
@section('page-title', 'Preinscripción de '.$p->nombre.' '.$p->apellido)

@section('content')
@if(session('success'))
    <div class="alert alert-success" role="status">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
<div class="ito-card p-4 mb-3" style="max-width: 760px">
    <dl class="row mb-0">
        <dt class="col-sm-4">Sede</dt><dd class="col-sm-8">{{ $p->sede?->nombre }}</dd>
        <dt class="col-sm-4">Bloque pedido</dt><dd class="col-sm-8">{{ $p->bloque?->nombre ?? 'Sin preferencia' }}</dd>
        <dt class="col-sm-4">DNI</dt><dd class="col-sm-8">{{ $p->dni ?: 'No informado' }}</dd>
        <dt class="col-sm-4">Nacimiento</dt><dd class="col-sm-8">{{ $p->fecha_nacimiento->format('d/m/Y') }}{{ $p->esMenor() ? ' (menor de 18)' : '' }}</dd>
        <dt class="col-sm-4">Teléfono</dt><dd class="col-sm-8">{{ $p->telefono }}</dd>
        <dt class="col-sm-4">Correo</dt><dd class="col-sm-8">{{ $p->email ?: 'No informado' }}</dd>
        @if($p->tutor_nombre)
            <dt class="col-sm-4">Tutor</dt><dd class="col-sm-8">{{ $p->tutor_nombre }} ({{ $p->tutor_vinculo }}) · {{ $p->tutor_telefono }}{{ $p->tutor_dni ? ' · DNI '.$p->tutor_dni : '' }}</dd>
        @endif
        @if($p->notas)
            <dt class="col-sm-4">Notas</dt><dd class="col-sm-8">{{ $p->notas }}</dd>
        @endif
        <dt class="col-sm-4">Estado</dt><dd class="col-sm-8">{{ ucfirst($p->estado) }}{{ $p->motivo_rechazo ? ' · '.$p->motivo_rechazo : '' }}</dd>
    </dl>
</div>

@if($p->estado === \App\Models\Preinscripcion::PENDIENTE)
    <div class="ito-card p-4 mb-3" style="max-width: 760px">
        <h2 class="h6">Aprobar e inscribir</h2>
        <p class="text-muted small">Crea el alumno en la sede y lo inscribe en el bloque elegido (si tiene cupo). Se avisa a la persona.</p>
        <form method="POST" action="{{ route('preinscripciones.aprobar', $p) }}" class="d-flex flex-wrap gap-2 align-items-end">
            @csrf
            <div>
                <label class="form-label small" for="bloque_id">Bloque</label>
                <select name="bloque_id" id="bloque_id" class="form-select">
                    <option value="">Sin bloque por ahora</option>
                    @foreach($p->sede?->bloques()->where('activo', true)->orderBy('nombre')->get() ?? [] as $b)
                        <option value="{{ $b->id }}" @selected($p->bloque_id === $b->id)>{{ $b->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn-primary">Aprobar</button>
        </form>
        <hr>
        <h2 class="h6">Rechazar</h2>
        <form method="POST" action="{{ route('preinscripciones.rechazar', $p) }}" class="d-flex flex-wrap gap-2 align-items-end">
            @csrf
            <div class="flex-grow-1">
                <label class="form-label small" for="motivo">Motivo (se le informa)</label>
                <input id="motivo" name="motivo" class="form-control" maxlength="255" placeholder="Ej.: no hay cupo en ese horario">
            </div>
            <button class="btn btn-outline-danger">Rechazar</button>
        </form>
    </div>
@endif
<a href="{{ route('preinscripciones.index') }}">Volver a la bandeja</a>
@endsection
