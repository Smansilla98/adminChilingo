@extends('layouts.app')

@section('title', 'Calendario de Eventos')
@section('page-title', 'Calendario')

@section('content')
<div class="row mb-3 mb-md-4">
    <div class="col-12">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
            <div>
                <h1 class="ito-page-title mb-0 mb-md-2">
                    <i class="bi bi-calendar-event"></i> Calendario de Eventos
                </h1>
                <p class="text-muted small mb-0 mt-1">Las clases fijas salen de los horarios de cada bloque. Para cambiarlos: <em>Bloques → Editar</em> y sumá día y hora.</p>
            </div>
            @if(auth()->user() && auth()->user()->isAdmin())
            <div class="d-flex gap-2">
                <a href="{{ route('eventos.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> <span class="d-none d-sm-inline">Nuevo </span>Evento
                </a>
                <a href="{{ route('shows.create') }}" class="btn btn-secondary">
                    <i class="bi bi-mic"></i> <span class="d-none d-sm-inline">Nuevo </span>Show
                </a>
            </div>
            @endif
        </div>
    </div>
</div>

<div class="card mb-3 mb-md-4">
    <div class="card-header">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
            <div>
                <h5 class="mb-0">
                    {{ $startDate->locale('es')->translatedFormat('F Y') }}
                </h5>
            </div>
            <div class="btn-group w-100 w-md-auto">
                <a href="{{ route('calendario.index', ['year' => $prevMonth->year, 'month' => $prevMonth->month]) }}"
                   class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-chevron-left"></i> <span class="d-none d-sm-inline">Anterior</span>
                </a>
                <a href="{{ route('calendario.index') }}" class="btn btn-sm btn-outline-secondary">
                    Hoy
                </a>
                <a href="{{ route('calendario.index', ['year' => $nextMonth->year, 'month' => $nextMonth->month]) }}"
                   class="btn btn-sm btn-outline-secondary">
                    <span class="d-none d-sm-inline">Siguiente </span><i class="bi bi-chevron-right"></i>
                </a>
            </div>
        </div>
    </div>
    <div class="card-body p-1 p-md-3">
        <div class="calendar-container">
            <table class="table table-bordered calendar-table mb-0">
                <thead>
                    <tr>
                        <th class="text-center calendar-header">Dom</th>
                        <th class="text-center calendar-header">Lun</th>
                        <th class="text-center calendar-header">Mar</th>
                        <th class="text-center calendar-header">Mié</th>
                        <th class="text-center calendar-header">Jue</th>
                        <th class="text-center calendar-header">Vie</th>
                        <th class="text-center calendar-header">Sáb</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $totalCells = $firstDayOfWeek + $daysInMonth;
                        $weeks = (int) ceil($totalCells / 7);
                    @endphp
                    @for($week = 0; $week < $weeks; $week++)
                    <tr>
                        @for($dayOfWeek = 0; $dayOfWeek < 7; $dayOfWeek++)
                            @php
                                $cellDay = ($week * 7) + $dayOfWeek - $firstDayOfWeek + 1;
                                $isCurrentMonth = $cellDay >= 1 && $cellDay <= $daysInMonth;
                                $isToday = $isCurrentMonth && $cellDay == now()->day && $startDate->year == now()->year && $startDate->month == now()->month;
                                $dateKey = $isCurrentMonth ? $startDate->copy()->addDays($cellDay - 1)->format('Y-m-d') : null;
                                $dayEvents = $dateKey && isset($eventsByDay[$dateKey]) ? $eventsByDay[$dateKey] : [];
                            @endphp
                            <td class="calendar-day {{ $isToday ? 'today' : '' }} {{ !$isCurrentMonth ? 'other-month' : '' }}">
                                <div class="day-number">{{ $isCurrentMonth ? $cellDay : '' }}</div>
                                <div class="day-events">
                                    @foreach($dayEvents as $item)
                                        @if($item['type'] === 'evento')
                                            @php $evento = $item['data']; @endphp
                                            <div class="event-item event-evento"
                                                 onclick="window.location.href='{{ route('eventos.show', $evento) }}'"
                                                 role="button"
                                                 tabindex="0"
                                                 title="{{ $evento->titulo }} - {{ $evento->fecha->format('d/m/Y') }}{{ $evento->hora_inicio ? ' ' . $evento->hora_inicio->format('H:i') . 'hs' : '' }}">
                                                <small class="event-text">
                                                    @if($evento->hora_inicio)
                                                        <span class="event-time">{{ $evento->hora_inicio->format('H:i') }}hs</span>
                                                    @endif
                                                    <span class="event-name">{{ \Illuminate\Support\Str::limit($evento->titulo, 15) }}</span>
                                                </small>
                                            </div>
                                        @elseif($item['type'] === 'bloque_taller')
                                            @php
                                                $bd = $item['data'];
                                                $bloqueT = $bd['bloque'];
                                                $horT = $bd['horario'];
                                                $urlT = $bd['url'];
                                                $hIni = $horT->hora_inicio;
                                                $hFin = $horT->hora_fin;
                                                $tIni = $hIni ? (\Carbon\Carbon::parse($hIni)->format('H:i')) : '';
                                                $tFin = $hFin ? (\Carbon\Carbon::parse($hFin)->format('H:i')) : '';
                                                $tipT = ($bloqueT->sede?->nombre ? $bloqueT->sede->nombre.' · ' : '').($horT->nombre_dia ?? '');
                                            @endphp
                                            <div class="event-item event-taller"
                                                 onclick="window.location.href='{{ $urlT }}'"
                                                 role="button"
                                                 tabindex="0"
                                                 title="Taller fijo: {{ $bloqueT->nombre }} · {{ $tIni }}–{{ $tFin }}hs · {{ $tipT }}">
                                                <small class="event-text">
                                                    @if($tIni)
                                                        <span class="event-time">{{ $tIni }}hs</span>
                                                    @endif
                                                    <span class="event-name">{{ \Illuminate\Support\Str::limit($bloqueT->nombre, 16) }}</span>
                                                </small>
                                            </div>
                                        @elseif($item['type'] === 'show')
                                            @php $show = $item['data']; @endphp
                                            <div class="event-item event-show"
                                                 onclick="window.location.href='{{ route('shows.show', $show) }}'"
                                                 role="button"
                                                 tabindex="0"
                                                 title="{{ $show->titulo }} - {{ $show->fecha->format('d/m/Y') }}{{ $show->hora_inicio ? ' ' . $show->hora_inicio->format('H:i') . 'hs' : '' }}">
                                                <small class="event-text">
                                                    @if($show->hora_inicio)
                                                        <span class="event-time">{{ $show->hora_inicio->format('H:i') }}hs</span>
                                                    @endif
                                                    <span class="event-name">{{ \Illuminate\Support\Str::limit($show->titulo, 12) }}</span>
                                                </small>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </td>
                        @endfor
                    </tr>
                    @endfor
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($listItems->isNotEmpty())
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Eventos, shows y talleres fijos — {{ $startDate->locale('es')->translatedFormat('F Y') }}</h5>
    </div>
    <div class="card-body p-2 p-md-3">
        <div class="list-group list-group-flush">
            @foreach($listItems as $item)
            <a href="{{ $item->url }}"
               class="list-group-item list-group-item-action event-list-item {{ $item->tipo === 'bloque_taller' ? 'event-list-item--taller' : '' }}">
                <div class="d-flex w-100 justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            @php
                                $badgeBg = $item->tipo === 'show' ? 'danger' : ($item->tipo === 'bloque_taller' ? 'success' : 'primary');
                            @endphp
                            <span class="badge bg-{{ $badgeBg }} event-status-badge">{{ $item->tipo_badge }}</span>
                            <h6 class="mb-0 event-title">{{ $item->titulo }}</h6>
                        </div>
                        <div class="event-meta mb-1">
                            <small class="text-muted d-flex flex-wrap align-items-center gap-2">
                                <span><i class="bi bi-calendar"></i> {{ $item->fecha->locale('es')->translatedFormat('d/m/Y') }}</span>
                                @if($item->hora_inicio)
                                    <span><i class="bi bi-clock"></i> {{ \Carbon\Carbon::parse($item->hora_inicio)->format('H:i') }}hs
                                        @if($item->tipo === 'bloque_taller' && isset($item->horario) && $item->horario->hora_fin)
                                            – {{ \Carbon\Carbon::parse($item->horario->hora_fin)->format('H:i') }}hs
                                        @endif
                                    </span>
                                @endif
                                @if($item->tipo === 'bloque_taller' && isset($item->horario))
                                    <span><i class="bi bi-arrow-repeat"></i> Cada {{ $item->horario->nombre_dia }}</span>
                                @endif
                                @if($item->tipo === 'bloque_taller' && isset($item->bloque) && $item->bloque->sede)
                                    <span><i class="bi bi-geo-alt"></i> {{ $item->bloque->sede->nombre }}</span>
                                @endif
                            </small>
                        </div>
                    </div>
                    <div class="text-end ms-2 flex-shrink-0">
                        <i class="bi bi-chevron-right event-arrow"></i>
                    </div>
                </div>
            </a>
            @endforeach
        </div>
    </div>
</div>
@endif

@push('styles')
<style>
/* Contenedor del calendario */
.calendar-container {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    width: 100%;
}

.calendar-table {
    width: 100%;
    table-layout: fixed;
    border-collapse: separate;
    border-spacing: 0;
}

.calendar-header {
    width: calc(100% / 7);
    padding: 0.5rem 0.25rem !important;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    background-color: rgba(0, 0, 0, 0.05);
}

@media (min-width: 768px) {
    .calendar-header {
        padding: 0.75rem 0.5rem !important;
        font-size: 0.875rem;
    }
}

.calendar-day {
    width: calc(100% / 7);
    height: 80px;
    vertical-align: top;
    padding: 4px;
    position: relative;
    border: 1px solid #dee2e6;
    word-wrap: break-word;
}

@media (min-width: 768px) {
    .calendar-day {
        height: 120px;
        padding: 8px;
    }
}

.calendar-day.other-month {
    background-color: #f8f9fa;
    color: #adb5bd;
}

.calendar-day.today {
    background-color: #e7f3ff;
    border: 2px solid #0d6efd !important;
    font-weight: 600;
}

.calendar-day.today .day-number {
    color: #0d6efd;
}

.day-number {
    font-weight: bold;
    margin-bottom: 2px;
    font-size: 0.875rem;
    line-height: 1.2;
}

@media (min-width: 768px) {
    .day-number {
        font-size: 1.1em;
        margin-bottom: 5px;
    }
}

.day-events {
    max-height: 60px;
    overflow-y: auto;
    overflow-x: hidden;
    -webkit-overflow-scrolling: touch;
}

@media (min-width: 768px) {
    .day-events {
        max-height: 80px;
    }
}

.day-events::-webkit-scrollbar {
    width: 3px;
}

.day-events::-webkit-scrollbar-track {
    background: transparent;
}

.day-events::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.2);
    border-radius: 3px;
}

.event-item {
    padding: 3px 4px;
    margin-bottom: 2px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.65rem;
    line-height: 1.3;
    display: block;
    width: 100%;
    box-sizing: border-box;
    transition: all 0.2s ease;
}

@media (min-width: 768px) {
    .event-item {
        padding: 4px 6px;
        font-size: 0.75rem;
        margin-bottom: 3px;
    }
}

.event-item:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
}

.event-text {
    display: flex;
    align-items: center;
    gap: 3px;
    white-space: nowrap;
    overflow: hidden;
}

.event-time {
    font-weight: 600;
    flex-shrink: 0;
}

.event-name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    flex: 1;
    min-width: 0;
}

.event-evento {
    background-color: #0d6efd;
    color: white;
}

.event-show {
    background-color: #dc3545;
    color: white;
    border-left: 3px solid #a71d2a;
}

.event-taller {
    background-color: #198754;
    color: #fff;
    border-left: 3px solid #146c43;
}

@media (max-width: 767.98px) {
    .calendar-container {
        margin: 0 -15px;
        padding: 0 15px;
    }
    .calendar-day {
        min-height: 80px;
    }
    .event-name {
        max-width: 60px;
    }
}

.event-list-item {
    padding: 0.75rem !important;
    border-left: 3px solid transparent;
    transition: all 0.2s ease;
}

.event-list-item:hover {
    background-color: rgba(13, 110, 253, 0.05);
    border-left-color: #0d6efd;
}

.event-status-badge {
    font-size: 0.7rem;
    padding: 0.25rem 0.5rem;
}

.event-title {
    font-size: 1rem;
    font-weight: 600;
}

.event-meta {
    font-size: 0.85rem;
}

.event-arrow {
    font-size: 1.25rem;
    color: #6c757d;
}

.event-list-item--taller:hover {
    background-color: rgba(25, 135, 84, 0.12);
    border-left-color: #198754;
}

.event-list-item--taller:hover .event-arrow {
    color: #198754;
}
</style>
@endpush
@endsection
