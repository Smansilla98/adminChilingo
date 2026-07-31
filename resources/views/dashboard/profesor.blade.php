@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Mi panel')

@section('content')
@php
    $nombre = trim(auth()->user()->name ?: auth()->user()->username ?: 'Profesor');
    $primer = explode(' ', $nombre)[0] ?: 'Profesor';
    $hora = (int) now()->format('G');
    $saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
    $alumnosTotal = $bloques->sum(fn ($b) => $b->alumnos ? $b->alumnos->count() : 0);
@endphp

<div class="hub">
    <div class="hub-hero">
        <div class="hub-hero-main">
            <p class="hub-eyebrow">Panel docente · ITO</p>
            <h1 class="hub-greeting">{{ $saludo }}, <em>{{ $primer }}</em>.</h1>
            <p class="hub-lead">Tus bloques, asistencias y comprobantes en un solo lugar.</p>
        </div>
        <dl class="hub-meta">
            <div>
                <dt>Fecha</dt>
                <dd>{{ now()->locale('es')->translatedFormat('d/m/Y') }}</dd>
            </div>
            <div>
                <dt>Rol</dt>
                <dd>Profesor</dd>
            </div>
            <div>
                <dt>Estado</dt>
                <dd><span class="hub-status-dot" aria-hidden="true"></span> Operativo</dd>
            </div>
        </dl>
    </div>

    <div class="hub-kpis hub-kpis--4">
        <a class="hub-kpi" href="{{ route('profesor.bloques') }}">
            <div class="hub-kpi-top">
                <span class="hub-kpi-label">Mis bloques</span>
                <span class="hub-kpi-icon"><i class="bi bi-collection" aria-hidden="true"></i></span>
            </div>
            <div class="hub-kpi-value">{{ $bloques->count() }}</div>
            <span class="hub-kpi-badge">Asignados</span>
        </a>
        <a class="hub-kpi" href="{{ route('profesor.eventos') }}">
            <div class="hub-kpi-top">
                <span class="hub-kpi-label">Próximos eventos</span>
                <span class="hub-kpi-icon"><i class="bi bi-calendar-event" aria-hidden="true"></i></span>
            </div>
            <div class="hub-kpi-value">{{ $proximosEventos->count() }}</div>
            <span class="hub-kpi-badge">Agenda</span>
        </a>
        <a class="hub-kpi" href="{{ route('profesor.alumnos') }}">
            <div class="hub-kpi-top">
                <span class="hub-kpi-label">Alumnos</span>
                <span class="hub-kpi-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
            </div>
            <div class="hub-kpi-value">{{ $alumnosTotal }}</div>
            <span class="hub-kpi-badge">En tus bloques</span>
        </a>
        <a class="hub-kpi" href="{{ route('comprobantes-cuota-alumnos.index', ['estado' => 'pendiente']) }}">
            <div class="hub-kpi-top">
                <span class="hub-kpi-label">Comprobantes</span>
                <span class="hub-kpi-icon"><i class="bi bi-upload" aria-hidden="true"></i></span>
            </div>
            <div class="hub-kpi-value">{{ $comprobantesCuotaPendientes ?? 0 }}</div>
            @if(($comprobantesCuotaPendientes ?? 0) > 0)
                <span class="hub-kpi-badge is-alert">Pendientes</span>
            @else
                <span class="hub-kpi-badge is-ok">Sin pendientes</span>
            @endif
        </a>
    </div>

    <section class="hub-section">
        <header class="hub-section-head">
            <h2 class="hub-section-title"><span class="hub-section-code">01</span> Accesos rápidos</h2>
        </header>
        <div class="hub-modules">
            <a class="hub-module" href="{{ route('profesor.asistencias.create') }}">
                <span class="hub-module-icon"><i class="bi bi-check2-square"></i></span>
                <span class="hub-module-body">
                    <span class="hub-module-title">Tomar asistencia</span>
                    <span class="hub-module-desc">Cargar presencia del día</span>
                </span>
            </a>
            <a class="hub-module" href="{{ route('profesor.bloques') }}">
                <span class="hub-module-icon"><i class="bi bi-collection"></i></span>
                <span class="hub-module-body">
                    <span class="hub-module-title">Mis bloques</span>
                    <span class="hub-module-desc">Grupos asignados</span>
                </span>
            </a>
            <a class="hub-module" href="{{ route('profesor.alumnos') }}">
                <span class="hub-module-icon"><i class="bi bi-people"></i></span>
                <span class="hub-module-body">
                    <span class="hub-module-title">Mis alumnos</span>
                    <span class="hub-module-desc">Listado por bloque</span>
                </span>
            </a>
            <a class="hub-module" href="{{ route('programa.index') }}">
                <span class="hub-module-icon"><i class="bi bi-journal-text"></i></span>
                <span class="hub-module-body">
                    <span class="hub-module-title">Programa</span>
                    <span class="hub-module-desc">Toques y material</span>
                </span>
            </a>
            <a class="hub-module" href="{{ route('programa.partituras.index') }}">
                <span class="hub-module-icon"><i class="bi bi-file-earmark-music"></i></span>
                <span class="hub-module-body">
                    <span class="hub-module-title">Partituras</span>
                    <span class="hub-module-desc">PDFs del cuadernillo</span>
                </span>
            </a>
            <a class="hub-module" href="{{ route('calendario.index') }}">
                <span class="hub-module-icon"><i class="bi bi-calendar3"></i></span>
                <span class="hub-module-body">
                    <span class="hub-module-title">Calendario</span>
                    <span class="hub-module-desc">Fechas y ensayos</span>
                </span>
            </a>
        </div>
    </section>

    <div class="hub-panels">
        <div class="hub-panel">
            <div class="hub-panel-head">
                <div class="hub-panel-title">Bloques asignados</div>
            </div>
            @forelse($bloques as $bloque)
                <div class="hub-list-item">
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold">{{ $bloque->nombre ?? 'Bloque' }}</div>
                        <div class="small text-muted">{{ $bloque->sede->nombre ?? '—' }} · {{ $bloque->alumnos?->count() ?? 0 }} alumnos</div>
                    </div>
                </div>
            @empty
                <p class="text-muted small mb-0">No tenés bloques asignados.</p>
            @endforelse
        </div>
        <div class="hub-panel">
            <div class="hub-panel-head">
                <div class="hub-panel-title">Próximos eventos</div>
                <a href="{{ route('profesor.eventos') }}" class="hub-panel-link">Ver →</a>
            </div>
            @forelse($proximosEventos as $ev)
                <div class="hub-list-item">
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold text-truncate">{{ $ev->titulo ?? $ev->nombre ?? 'Evento' }}</div>
                        <div class="small text-muted">{{ $ev->fecha?->locale('es')->translatedFormat('d M Y') ?? '—' }}</div>
                    </div>
                </div>
            @empty
                <p class="text-muted small mb-0">Sin eventos próximos.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
