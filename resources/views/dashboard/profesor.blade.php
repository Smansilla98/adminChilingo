@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Mi panel')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header fw-semibold"><i class="bi bi-grid-3x3-gap"></i> Accesos rápidos</div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 prof-quick-links">
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('programa.index') }}">Programa</a>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('calendario.index') }}">Calendario</a>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('profesor.bloques') }}">Mis bloques</a>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('profesor.alumnos') }}">Mis alumnos</a>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('profesor.eventos') }}">Mis eventos</a>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('profesor.asistencias.create') }}">Tomar asistencia</a>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('comprobantes-cuota-alumnos.index') }}">Comprobantes de cuota</a>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('comprobante-cuota-public.create') }}" target="_blank" rel="noopener">Formulario público (alumnos)</a>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-collection"></i> Mis bloques</h5>
                <h2>{{ $bloques->count() }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-calendar-event"></i> Próximos eventos</h5>
                <h2>{{ $proximosEventos->count() }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-people"></i> Alumnos (total)</h5>
                <h2>{{ $bloques->sum(fn($b) => $b->alumnos ? $b->alumnos->count() : 0) }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card {{ ($comprobantesCuotaPendientes ?? 0) > 0 ? 'border-warning' : '' }}">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-upload"></i> Comprobantes alumnos</h5>
                <h2>{{ $comprobantesCuotaPendientes ?? 0 }}</h2>
                <a href="{{ route('comprobantes-cuota-alumnos.index', ['estado' => 'pendiente']) }}" class="small">Ver pendientes →</a>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-collection"></i> Bloques asignados</div>
            <div class="card-body">
                @if($bloques->count() > 0)
                <ul class="list-group">
                    @foreach($bloques as $bloque)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong>{{ $bloque->nombre ?? 'Bloque' }}</strong>
                            @if($bloque->sede ?? null)
                            <br><span class="badge bg-secondary">{{ $bloque->sede->nombre }}</span>
                            @endif
                        </div>
                        @if($bloque->alumnos ?? null)
                        <span class="badge bg-primary rounded-pill">{{ $bloque->alumnos->count() }} alumnos</span>
                        @endif
                    </li>
                    @endforeach
                </ul>
                @else
                <p class="text-muted mb-0">Aún no tenés bloques asignados.</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-calendar-event"></i> Próximos eventos</div>
            <div class="card-body">
                @if($proximosEventos->count() > 0)
                <ul class="list-group">
                    @foreach($proximosEventos as $evento)
                    <li class="list-group-item">
                        <strong>{{ $evento->titulo ?? 'Evento' }}</strong><br>
                        <small>
                            {{ isset($evento->fecha) && $evento->fecha ? $evento->fecha->format('d/m/Y') : '' }}
                            @if(isset($evento->hora_inicio) && $evento->hora_inicio)
                            - {{ $evento->hora_inicio->format('H:i') }}
                            @endif
                        </small>
                        @if(isset($evento->sede) && $evento->sede)
                        <br><span class="badge bg-secondary">{{ $evento->sede->nombre }}</span>
                        @endif
                    </li>
                    @endforeach
                </ul>
                @else
                <p class="text-muted mb-0">No hay eventos próximos.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
