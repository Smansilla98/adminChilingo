@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Mi panel')

@section('content')
<div class="row">
    <div class="col-md-4">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-collection"></i> Mis bloques</h5>
                <h2>{{ $bloques->count() }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-info">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-calendar-event"></i> Próximos eventos</h5>
                <h2>{{ $proximosEventos->count() }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-success">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-people"></i> Alumnos (total)</h5>
                <h2>{{ $bloques->sum(fn($b) => $b->alumnos ? $b->alumnos->count() : 0) }}</h2>
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
