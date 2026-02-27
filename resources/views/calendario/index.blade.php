@extends('layouts.app')

@section('title', 'Calendario')
@section('page-title', 'Calendario')

@section('content')
<ul class="nav nav-tabs mb-3" id="calendarioTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="cal-tab" data-bs-toggle="tab" data-bs-target="#cal" type="button" role="tab">Vista calendario</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="shows-tab" data-bs-toggle="tab" data-bs-target="#shows" type="button" role="tab">Próximos shows</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="horarios-tab" data-bs-toggle="tab" data-bs-target="#horarios" type="button" role="tab">Horarios por bloque</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="eventos-tab" data-bs-toggle="tab" data-bs-target="#eventos" type="button" role="tab">Próximos eventos</button>
    </li>
</ul>

<div class="tab-content" id="calendarioTabContent">
    <div class="tab-pane fade show active" id="cal" role="tabpanel">
        <div class="card shadow-sm">
            <div class="card-header py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="mb-0">Calendario</h5>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <select id="filtroSede" class="form-select form-select-sm" style="width: auto;">
                        <option value="">Todas las sedes</option>
                        @foreach($sedes as $sede)
                        <option value="{{ $sede->id }}">{{ $sede->nombre }}</option>
                        @endforeach
                    </select>
                    <select id="filtroProfesor" class="form-select form-select-sm" style="width: auto;">
                        <option value="">Todos los profesores</option>
                        @foreach($profesores as $profesor)
                        <option value="{{ $profesor->id }}">{{ $profesor->nombre }}</option>
                        @endforeach
                    </select>
                    @if(auth()->user()->isAdmin())
                    <a href="{{ route('eventos.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle"></i> Nuevo evento</a>
                    <a href="{{ route('shows.create') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-mic"></i> Nuevo show</a>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <div id="calendar"></div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="shows" role="tabpanel">
        <div class="card shadow-sm">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Próximos shows</h5>
                @if(auth()->user()->isAdmin())
                <a href="{{ route('shows.index') }}" class="btn btn-primary btn-sm">Ver todos / Gestionar</a>
                @endif
            </div>
            <div class="card-body">
                @forelse($shows as $s)
                <div class="d-flex justify-content-between align-items-start border-bottom pb-2 mb-2">
                    <div>
                        <strong>{{ $s->titulo }}</strong><br>
                        <small class="text-muted">{{ $s->fecha->format('d/m/Y') }} @if($s->hora_inicio) {{ $s->hora_inicio->format('H:i') }} @endif — {{ $s->lugar ?? 'Sin lugar' }}</small><br>
                        @if($s->convocatoria_abierta)
                        <span class="badge bg-info mt-1">Convocatoria abierta</span>
                        @else
                        <small>{{ $s->bloques->pluck('nombre')->join(', ') }}</small>
                        @endif
                    </div>
                    @if(auth()->user()->isAdmin())
                    <a href="{{ route('shows.show', $s) }}" class="btn btn-sm btn-outline-secondary">Ver</a>
                    @endif
                </div>
                @empty
                <p class="text-muted mb-0">No hay shows próximos.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="horarios" role="tabpanel">
        <div class="card shadow-sm">
            <div class="card-header py-3">
                <h5 class="mb-0">Días y horarios por bloque</h5>
                <small class="text-muted">Distinguidos por sede y profesor (colores)</small>
            </div>
            <div class="card-body">
                @php
                    $colores = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796', '#5a5c69'];
                    $bySede = $bloques->groupBy('sede_id');
                @endphp
                @foreach($bySede as $sedeId => $bloquesSede)
                @php $sede = $bloquesSede->first()->sede; @endphp
                <h6 class="mt-3 mb-2">{{ $sede ? $sede->nombre : 'Sin sede' }}</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th>Bloque</th>
                                <th>Profesor</th>
                                <th>Día(s)</th>
                                <th>Horario</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($bloquesSede as $i => $b)
                            <tr style="background-color: {{ $colores[$i % count($colores)] }}20;">
                                <td><span class="badge" style="background-color: {{ $colores[$i % count($colores)] }};">{{ $b->nombre }}</span></td>
                                <td>{{ $b->profesor?->nombre ?? '—' }}</td>
                                <td>
                                    @forelse($b->horarios->groupBy('dia_semana') as $dia => $hrs)
                                    {{ \App\Models\BloqueHorario::DIAS_SEMANA[$dia] ?? $dia }}@if(!$loop->last), @endif
                                    @empty —
                                    @endforelse
                                </td>
                                <td>
                                    @foreach($b->horarios as $h)
                                    {{ \Carbon\Carbon::parse($h->hora_inicio)->format('H:i') }}-{{ \Carbon\Carbon::parse($h->hora_fin)->format('H:i') }}@if(!$loop->last) / @endif
                                    @endforeach
                                    @if($b->horarios->isEmpty()) — @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endforeach
                @if($bloques->isEmpty())
                <p class="text-muted mb-0">No hay bloques con horarios cargados. Edite cada bloque para agregar días y horarios.</p>
                @endif
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="eventos" role="tabpanel">
        <div class="card shadow-sm">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Próximos eventos</h5>
                @if(auth()->user()->isAdmin())
                <a href="{{ route('eventos.index') }}" class="btn btn-primary btn-sm">Ver todos / Gestionar</a>
                @endif
            </div>
            <div class="card-body">
                @forelse($eventos as $e)
                <div class="d-flex justify-content-between align-items-start border-bottom pb-2 mb-2">
                    <div>
                        <strong>{{ $e->titulo }}</strong>
                        <span class="badge bg-secondary ms-1">{{ $e->tipo_evento }}</span><br>
                        <small class="text-muted">{{ $e->fecha->format('d/m/Y') }} @if($e->hora_inicio) {{ $e->hora_inicio->format('H:i') }} @endif — {{ $e->sede?->nombre ?? '' }}</small>
                        @if($e->descripcion)<br><small>{{ Str::limit($e->descripcion, 80) }}</small>@endif
                    </div>
                    @if(auth()->user()->isAdmin())
                    <a href="{{ route('eventos.show', $e) }}" class="btn btn-sm btn-outline-secondary">Ver</a>
                    @endif
                </div>
                @empty
                <p class="text-muted mb-0">No hay eventos próximos.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/main.min.css" rel="stylesheet">
<style>
    #calendar { max-width: 100%; }
    .fc-event-show { background: #e74a3b !important; border-color: #e74a3b !important; }
    .fc-event-tipo-aniversario { background: #1cc88a !important; }
    .fc-event-tipo-fiesta { background: #f6c23e !important; }
    .fc-event-tipo-rifa { background: #36b9cc !important; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/main.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/locales/es.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'es',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: function(fetchInfo, successCallback, failureCallback) {
            const params = new URLSearchParams({
                start: fetchInfo.startStr,
                end: fetchInfo.endStr
            });
            const sedeId = document.getElementById('filtroSede')?.value;
            const profesorId = document.getElementById('filtroProfesor')?.value;
            if (sedeId) params.append('sede_id', sedeId);
            if (profesorId) params.append('profesor_id', profesorId);
            fetch('{{ route("calendario.eventos") }}?' + params.toString())
                .then(r => r.json())
                .then(data => successCallback(data))
                .catch(err => failureCallback(err));
        },
        eventClick: function(info) {
            if (info.event.url) info.jsEvent.preventDefault(), window.location.href = info.event.url;
        }
    });
    calendar.render();
    document.getElementById('filtroSede')?.addEventListener('change', () => calendar.refetchEvents());
    document.getElementById('filtroProfesor')?.addEventListener('change', () => calendar.refetchEvents());
});
</script>
@endpush
@endsection
