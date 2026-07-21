@extends('layouts.app')

@section('title', 'Asistencias')
@section('page-title', 'Asistencias')

@section('content')
<div class="ito-page">
    <div class="ito-page-head">
        <div>
            <h1 class="ito-page-title">
                @if(!empty($vistaLista))
                    Historial de asistencias
                @else
                    Matriz de asistencias
                @endif
            </h1>
            <p class="ito-page-sub">Control de presencia por bloque</p>
        </div>
        <div class="ito-page-actions">
            @if(empty($vistaLista))
            <a href="{{ route('asistencias.create') }}" class="btn btn-secondary btn-sm">
                <i class="bi bi-calendar-plus"></i> Cargar un día
            </a>
            @endif
            <a href="{{ route('asistencias.index', array_merge(request()->except('vista'), ['vista' => 'lista'])) }}" class="btn btn-secondary btn-sm">
                <i class="bi bi-list-ul"></i> Vista lista
            </a>
            @if(!empty($vistaLista))
            <a href="{{ route('asistencias.index', request()->except('vista')) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-grid-3x3"></i> Volver a matriz
            </a>
            @endif
        </div>
    </div>
    <div class="ito-card">
    <div class="p-3">
        @if(!empty($vistaLista))
        <form method="GET" class="ito-toolbar-filters d-flex flex-wrap align-items-end gap-2 mb-3">
            <input type="hidden" name="vista" value="lista">
            <div class="ito-field">
                <label>Bloque</label>
                <select name="bloque_id" class="form-select">
                    <option value="">Todos los bloques</option>
                    @foreach($bloques as $b)
                    <option value="{{ $b->id }}" @selected(request('bloque_id') == $b->id)>{{ $b->nombre }} ({{ $b->sede->nombre ?? '' }})</option>
                    @endforeach
                </select>
            </div>
            <div class="ito-field">
                <label>Fecha</label>
                <input type="date" name="fecha" class="form-control" value="{{ request('fecha') }}">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
        </form>
        @include('asistencias.index-lista')
        @else
        <p class="text-muted small mb-2">
            Elegí <strong>bloque</strong> y <strong>mes</strong>. Cada columna es un día de clase.
        </p>
        <div class="asist-legend" aria-label="Leyenda de asistencia">
            <span><i class="asist-badge-p" aria-hidden="true">P</i> Presente</span>
            <span><i class="asist-badge-t" aria-hidden="true">T</i> Tarde</span>
            <span><i class="asist-badge-j" aria-hidden="true">J</i> Justificado</span>
            <span><i class="asist-badge-i" aria-hidden="true">I</i> Ausente sin aviso</span>
            <span><i style="background:var(--s3);color:var(--muted);border:1px solid var(--border)">—</i> Sin marcar</span>
        </div>

        <form method="GET" class="mb-3">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Bloque</label>
                    <select name="bloque_id" class="form-select" required>
                        <option value="">Elegí bloque…</option>
                        @foreach($bloques as $b)
                        <option value="{{ $b->id }}" {{ (string)request('bloque_id') === (string)$b->id ? 'selected' : '' }}>{{ $b->nombre }} — {{ $b->sede->nombre ?? '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Mes</label>
                    <select name="mes" class="form-select">
                        @foreach(range(1, 12) as $m)
                        <option value="{{ $m }}" {{ (int)$mes === $m ? 'selected' : '' }}>
                            {{ ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'][$m] }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Año</label>
                    <input type="number" name="año" class="form-control" min="2000" max="2100" value="{{ $año }}">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">Ver matriz</button>
                </div>
            </div>
        </form>

        @if(!empty($matrix) && $bloque && $fechas->isNotEmpty())
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <div>
                <strong>{{ $bloque->nombre }}</strong>
                @if($bloque->sede)
                    <span class="text-muted"> · {{ $bloque->sede->nombre }}</span>
                @endif
                @if($bloque->profesor)
                    <span class="text-muted"> · Profe: {{ $bloque->profesor->nombre }}</span>
                @endif
            </div>
        </div>

        <form action="{{ route('asistencias.matrix.update') }}" method="POST" class="asistencias-matrix-form">
            @csrf
            <input type="hidden" name="bloque_id" value="{{ $bloque->id }}">
            <input type="hidden" name="mes" value="{{ $mes }}">
            <input type="hidden" name="año" value="{{ $año }}">

            <div class="table-responsive asistencias-matrix-wrap">
                <table class="table table-sm table-bordered align-middle asistencias-matrix">
                    <thead>
                        <tr>
                            <th class="sticky-col">Alumno</th>
                            <th class="sticky-col-2">Instrumento</th>
                            @foreach($fechas as $f)
                            @php
                                $diaAbbr = [1=>'lun',2=>'mar',3=>'mié',4=>'jue',5=>'vie',6=>'sáb',7=>'dom'][$f->dayOfWeekIso] ?? '';
                            @endphp
                            <th class="text-center text-nowrap col-fecha" title="{{ $diaAbbr }}">
                                <div class="small text-muted">{{ $diaAbbr }}</div>
                                <div>{{ $f->format('d/m') }}</div>
                            </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($alumnos as $alumno)
                        <tr>
                            <td class="sticky-col">{{ $alumno->nombre_apellido }}</td>
                            <td class="sticky-col-2 text-muted small">{{ $alumno->instrumento_principal ?? '—' }}</td>
                            @foreach($fechas as $f)
                            @php
                                $ymd = $f->format('Y-m-d');
                                $key = $alumno->id.'|'.$ymd;
                                $reg = $asistenciasMap[$key] ?? null;
                                $tipo = $reg?->tipo_asistencia;
                                $letra = \App\Models\Asistencia::letraTipo($tipo);
                                $cellClass = match($letra) { 'P' => 'asist-cell-p', 'T' => 'asist-cell-t', 'J' => 'asist-cell-j', 'I' => 'asist-cell-i', default => '' };
                            @endphp
                            <td class="text-center p-1 {{ $cellClass }}">
                                <label class="visually-hidden" for="asist-{{ $alumno->id }}-{{ $f->format('Ymd') }}">
                                    {{ $alumno->nombre_apellido }}, {{ $f->format('d/m/Y') }}
                                </label>
                                <select
                                    id="asist-{{ $alumno->id }}-{{ $f->format('Ymd') }}"
                                    name="cells[{{ $alumno->id }}][{{ $ymd }}]"
                                    class="form-select form-select-sm asistencia-cell-select"
                                    data-letra="{{ $letra ?: '—' }}"
                                    aria-label="{{ $alumno->nombre_apellido }}, {{ $f->translatedFormat('l d/m') }}: {{ $letra ? (['P'=>'Presente','T'=>'Tarde','J'=>'Justificado','I'=>'Ausente'][$letra] ?? $letra) : 'Sin marcar' }}"
                                >
                                    <option value="" {{ $tipo === null || $tipo === '' ? 'selected' : '' }}>—</option>
                                    <option value="presente" {{ $tipo === 'presente' ? 'selected' : '' }}>P · Presente</option>
                                    <option value="tarde" {{ $tipo === 'tarde' ? 'selected' : '' }}>T · Tarde</option>
                                    <option value="ausencia_justificada" {{ in_array($tipo, ['ausencia_justificada', 'justificado'], true) ? 'selected' : '' }}>J · Justificado</option>
                                    <option value="ausencia_injustificada" {{ in_array($tipo, ['ausencia_injustificada', 'ausente'], true) ? 'selected' : '' }}>I · Ausente</option>
                                </select>
                            </td>
                            @endforeach
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ 2 + $fechas->count() }}" class="text-center text-muted">No hay alumnos activos en este bloque.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($alumnos->isNotEmpty())
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Guardar matriz</button>
            </div>
            @endif
        </form>
        @elseif(!empty($matrix) && $bloque && $fechas->isEmpty())
        <div class="alert alert-warning mb-0">No hay días de clase en este mes según el calendario del bloque (o no hay horarios y el mes no tiene viernes).</div>
        @elseif(request()->filled('bloque_id'))
        <div class="alert alert-info mb-0">No se encontró el bloque o está inactivo.</div>
        @else
        <div class="alert alert-secondary mb-0">Elegí bloque y mes, luego tocá <strong>Ver matriz</strong>.</div>
        @endif
        @endif
    </div>
    </div>
</div>
@endsection
