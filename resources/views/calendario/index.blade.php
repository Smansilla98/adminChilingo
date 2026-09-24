@extends('layouts.app')

@section('title', 'Calendario de Eventos')
@section('page-title', 'Calendario')

@section('content')
<x-ito.shell-page
    title="Calendario de eventos"
    eyebrow="Agenda"
    subtitle="Las clases fijas salen de los horarios de cada bloque. Para cambiarlos: Bloques → Editar."
>
    <x-slot:actions>
        @if(auth()->user() && auth()->user()->isAdmin())
        <a href="{{ route('eventos.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle"></i> Evento
        </a>
        <a href="{{ route('shows.create') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-mic"></i> Show
        </a>
        @endif
    </x-slot:actions>

<div class="ito-card mb-3 mb-md-4">
    <div class="card-header">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
            <div>
                <h2 class="h5 mb-0 text-capitalize">
                    {{ $startDate->locale('es')->translatedFormat('F Y') }}
                </h2>
                <div class="cal-legend mt-2" aria-label="Leyenda del calendario">
                    <span class="cal-legend-item cal-legend-item--evento">Evento</span>
                    <span class="cal-legend-item cal-legend-item--taller">Clase / taller</span>
                    <span class="cal-legend-item cal-legend-item--show">Show</span>
                </div>
            </div>
            <div class="btn-group" role="group" aria-label="Cambiar mes">
                <a href="{{ route('calendario.index', ['year' => $prevMonth->year, 'month' => $prevMonth->month]) }}"
                   class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-chevron-left"></i> <span class="d-none d-sm-inline">Anterior</span>
                </a>
                <a href="{{ route('calendario.index') }}" class="btn btn-sm btn-outline-secondary">
                    Este mes
                </a>
                <a href="{{ route('calendario.index', ['year' => $nextMonth->year, 'month' => $nextMonth->month]) }}"
                   class="btn btn-sm btn-outline-secondary">
                    <span class="d-none d-sm-inline">Siguiente </span><i class="bi bi-chevron-right"></i>
                </a>
            </div>
        </div>
    </div>
    <div class="card-body p-2 p-md-3">
        <p class="cal-grid-note text-muted small mb-0" style="display:none">En el celular te mostramos la agenda del mes en forma de lista, más abajo.</p>
        <div class="calendar-container">
            <table class="table calendar-table mb-0" data-ito-no-cards>
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
                                <div class="day-number">@if($isToday)<span class="visually-hidden">Hoy, </span>@endif{{ $isCurrentMonth ? $cellDay : '' }}</div>
                                <div class="day-events">
                                    @foreach($dayEvents as $item)
                                        @if($item['type'] === 'evento')
                                            @php $evento = $item['data']; @endphp
                                            <a class="event-item event-evento"
                                                 href="{{ route('eventos.show', $evento) }}"
                                                 aria-label="Evento: {{ $evento->titulo }}{{ $evento->hora_inicio ? ', '.$evento->hora_inicio->format('H:i').' hs' : '' }}">
                                                <small class="event-text">
                                                    @if($evento->hora_inicio)
                                                        <span class="event-time">{{ $evento->hora_inicio->format('H:i') }}hs</span>
                                                    @endif
                                                    <span class="event-name">{{ \Illuminate\Support\Str::limit($evento->titulo, 40) }}</span>
                                                </small>
                                            </a>
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
                                            <a class="event-item event-taller"
                                                 href="{{ $urlT }}"
                                                 aria-label="Taller: {{ $bloqueT->nombre }}{{ $tIni ? ', '.$tIni.' hs' : '' }}">
                                                <small class="event-text">
                                                    @if($tIni)
                                                        <span class="event-time">{{ $tIni }}hs</span>
                                                    @endif
                                                    <span class="event-name">{{ \Illuminate\Support\Str::limit($bloqueT->nombre, 40) }}</span>
                                                </small>
                                            </a>
                                        @elseif($item['type'] === 'show')
                                            @php $show = $item['data']; @endphp
                                            <a class="event-item event-show"
                                                 href="{{ route('shows.show', $show) }}"
                                                 aria-label="Show: {{ $show->titulo }}{{ $show->hora_inicio ? ', '.$show->hora_inicio->format('H:i').' hs' : '' }}">
                                                <small class="event-text">
                                                    @if($show->hora_inicio)
                                                        <span class="event-time">{{ $show->hora_inicio->format('H:i') }}hs</span>
                                                    @endif
                                                    <span class="event-name">{{ \Illuminate\Support\Str::limit($show->titulo, 40) }}</span>
                                                </small>
                                            </a>
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
<div class="ito-card">
    <div class="card-header">
        <h2 class="h5 mb-0">Agenda de {{ $startDate->locale('es')->translatedFormat('F Y') }}</h2>
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
                                $tonoTipo = $item->tipo === 'show' ? 'danger' : ($item->tipo === 'bloque_taller' ? 'success' : 'info');
                            @endphp
                            <x-ito.status :tone="$tonoTipo" :label="$item->tipo_badge" />
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
</x-ito.shell-page>

@push('styles')
<style>
.calendar-container { width: 100%; overflow-x: auto; }
.calendar-table { width: 100%; table-layout: fixed; border-collapse: collapse; }
.calendar-table > thead > tr > th.calendar-header {
    padding: 8px 4px;
    background: var(--s2);
    color: var(--muted);
    font-size: 12px;
    font-weight: 600;
    text-align: center;
    border: 1px solid var(--border);
}
.calendar-table > tbody > tr > td.calendar-day {
    height: 118px;
    padding: 6px;
    vertical-align: top;
    border: 1px solid var(--border);
    background: var(--s1);
    color: var(--text);
}
.calendar-day.other-month { background: var(--s2) !important; }
.day-number {
    display: inline-grid;
    place-items: center;
    min-width: 26px;
    height: 26px;
    margin-bottom: 4px;
    border-radius: 50%;
    color: var(--text-2);
    font-size: 13px;
    font-weight: 600;
}
.calendar-day.today .day-number { background: var(--primary); color: var(--primary-on); }
.day-events { display: grid; gap: 3px; max-height: 76px; overflow-y: auto; scrollbar-width: thin; }
.event-item {
    display: block;
    padding: 3px 6px;
    border-left: 3px solid var(--info);
    border-radius: var(--radius-xs);
    background: var(--info-soft);
    color: var(--text);
    font-size: 12px;
    line-height: 1.35;
    text-decoration: none;
}
.event-item:hover { color: var(--text); filter: brightness(0.97); }
.event-text { display: flex; gap: 4px; min-width: 0; overflow: hidden; white-space: nowrap; }
.event-time { flex-shrink: 0; font-weight: 700; font-variant-numeric: tabular-nums; }
.event-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; }
.event-taller { border-left-color: var(--success); background: var(--success-soft); }
.event-show { border-left-color: var(--danger); background: var(--danger-soft); }
.cal-legend { display: flex; flex-wrap: wrap; gap: 12px; }
.cal-legend-item { display: inline-flex; align-items: center; gap: 6px; color: var(--text-2); font-size: 12.5px; }
.cal-legend-item::before { content: ''; width: 12px; height: 12px; border-radius: 3px; border-left: 3px solid var(--info); background: var(--info-soft); }
.cal-legend-item--taller::before { border-left-color: var(--success); background: var(--success-soft); }
.cal-legend-item--show::before { border-left-color: var(--danger); background: var(--danger-soft); }
.event-list-item { padding: 12px 14px !important; border-left: 3px solid transparent; }
.event-list-item:hover { background: var(--s2); border-left-color: var(--accent); }
.event-title { font-size: 15px; font-weight: 600; }
.event-arrow { color: var(--muted); }
/* En celular la grilla mensual no entra: se muestra la agenda del mes. */
@media (max-width: 767.98px) {
    .calendar-container { display: none; }
    .cal-grid-note { display: block !important; }
}
</style>
@endpush
@endsection
