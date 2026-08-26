@extends('layouts.app')

@section('title', 'Asistencias')
@section('page-title', 'Asistencias')

@section('content')
@php
    $esProfeSolo = auth()->user()?->isProfesor() && ! auth()->user()?->isAdmin();
    $matrixIndexRoute = $esProfeSolo ? 'profesor.asistencias.matrix' : 'asistencias.index';
    $diaCreateRoute = $esProfeSolo ? 'profesor.asistencias.create' : 'asistencias.create';
@endphp
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
            <p class="ito-page-sub">Control de presencia por bloque · sede visible en cada filtro</p>
        </div>
        <div class="ito-page-actions">
            @if(empty($vistaLista))
            <a href="{{ route($diaCreateRoute) }}" class="btn btn-secondary btn-sm">
                <i class="bi bi-calendar-plus"></i> Cargar un día
            </a>
            @endif
            @unless($esProfeSolo)
            <a href="{{ route('asistencias.index', array_merge(request()->except('vista'), ['vista' => 'lista'])) }}" class="btn btn-secondary btn-sm">
                <i class="bi bi-list-ul"></i> Vista lista
            </a>
            @endunless
            @if(!empty($vistaLista))
            <a href="{{ route($matrixIndexRoute, request()->except('vista')) }}" class="btn btn-primary btn-sm">
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
            <span><i class="asist-badge-f" aria-hidden="true">F</i> Feriado</span>
            <span><i class="asist-badge-s" aria-hidden="true">S</i> Sin clases</span>
            <span><i style="background:var(--s3);color:var(--muted);border:1px solid var(--border)">—</i> Sin marcar</span>
        </div>

        <form method="GET" action="{{ route($matrixIndexRoute) }}" class="mb-3">
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
        <div class="asist-mobile-day d-md-none mb-3">
            <p class="fw-semibold mb-2">En el teléfono: pasá lista de un día y seguí con la clase.</p>
            <a class="btn btn-primary btn-lg w-100" href="{{ route($diaCreateRoute, ['bloque_id' => $bloque->id, 'fecha' => now()->toDateString()]) }}">
                Asistencia de hoy
            </a>
            <p class="small text-muted mt-2 mb-0">La planilla del mes está pensada para escritorio.</p>
        </div>
        <div class="d-none d-md-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
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

        <form action="{{ route($matrixUpdateRoute ?? 'asistencias.matrix.update') }}" method="POST" class="asistencias-matrix-form d-none d-md-block" id="asistencias-matrix-form">
            @csrf
            <input type="hidden" name="bloque_id" value="{{ $bloque->id }}">
            <input type="hidden" name="mes" value="{{ $mes }}">
            <input type="hidden" name="año" value="{{ $año }}">

            @if(($marcadoresEspeciales ?? 0) > 0)
            <div class="alert alert-info py-2 small mb-3">
                Este mes ya tiene <strong>{{ $marcadoresEspeciales }}</strong> marca(s) de feriado o sin clases. Revisá antes de pisar columnas enteras.
            </div>
            @endif

            @if($alumnos->isNotEmpty())
            <div class="asist-bulk-bar mb-3" role="group" aria-label="Asignación masiva de asistencia">
                <div class="ito-field mb-0">
                    <label for="asist-bulk-tipo">Asignación masiva</label>
                    <select id="asist-bulk-tipo" class="form-select form-select-sm">
                        <option value="">— Sin marcar</option>
                        <option value="presente" selected>P · Presente</option>
                        <option value="tarde">T · Tarde</option>
                        <option value="ausencia_justificada">J · Justificado</option>
                        <option value="ausencia_injustificada">I · Ausente</option>
                        <option value="feriado">F · Feriado</option>
                        <option value="sin_clases">S · Sin clases</option>
                    </select>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" id="asist-bulk-all" title="Aplica el tipo elegido a todas las celdas">
                    Aplicar a toda la planilla
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="asist-bulk-presentes" title="Marca todos presentes">
                    Todos presentes
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="asist-copy-prev" title="Copia cada columna desde el día anterior de la matriz">
                    Copiar día anterior
                </button>
                <span class="text-muted small d-none d-md-inline">O usá ↓ en cada día.</span>
            </div>
            @endif

            <div class="table-responsive asistencias-matrix-wrap">
                <table class="table table-sm table-bordered align-middle asistencias-matrix">
                    <thead>
                        <tr>
                            <th class="sticky-col">Alumno</th>
                            <th class="sticky-col-2">Instrumento</th>
                            @foreach($fechas as $f)
                            @php
                                $ymd = $f->format('Y-m-d');
                                $diaAbbr = [1=>'lun',2=>'mar',3=>'mié',4=>'jue',5=>'vie',6=>'sáb',7=>'dom'][$f->dayOfWeekIso] ?? '';
                            @endphp
                            <th class="text-center text-nowrap col-fecha" title="{{ $diaAbbr }} {{ $f->format('d/m/Y') }}">
                                <div class="small text-muted">{{ $diaAbbr }}</div>
                                <div>{{ $f->format('d/m') }}</div>
                                @if($alumnos->isNotEmpty())
                                <button
                                    type="button"
                                    class="btn btn-link btn-sm p-0 asist-bulk-col"
                                    data-fecha="{{ $ymd }}"
                                    title="Aplicar tipo masivo a todos los alumnos el {{ $f->format('d/m') }}"
                                    aria-label="Aplicar tipo masivo al {{ $f->format('d/m/Y') }}"
                                >↓</button>
                                @endif
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
                                $cellClass = match($letra) {
                                    'P' => 'asist-cell-p',
                                    'T' => 'asist-cell-t',
                                    'J' => 'asist-cell-j',
                                    'I' => 'asist-cell-i',
                                    'F' => 'asist-cell-f',
                                    'S' => 'asist-cell-s',
                                    default => '',
                                };
                                $ariaTipo = match($letra) {
                                    'P' => 'Presente',
                                    'T' => 'Tarde',
                                    'J' => 'Justificado',
                                    'I' => 'Ausente',
                                    'F' => 'Feriado',
                                    'S' => 'Sin clases',
                                    default => 'Sin marcar',
                                };
                            @endphp
                            <td class="text-center p-1 {{ $cellClass }}">
                                <label class="visually-hidden" for="asist-{{ $alumno->id }}-{{ $f->format('Ymd') }}">
                                    {{ $alumno->nombre_apellido }}, {{ $f->format('d/m/Y') }}
                                </label>
                                <select
                                    id="asist-{{ $alumno->id }}-{{ $f->format('Ymd') }}"
                                    name="cells[{{ $alumno->id }}][{{ $ymd }}]"
                                    class="form-select form-select-sm asistencia-cell-select"
                                    data-fecha="{{ $ymd }}"
                                    data-letra="{{ $letra ?: '—' }}"
                                    aria-label="{{ $alumno->nombre_apellido }}, {{ $f->translatedFormat('l d/m') }}: {{ $ariaTipo }}"
                                >
                                    <option value="" {{ $tipo === null || $tipo === '' ? 'selected' : '' }}>—</option>
                                    <option value="presente" {{ $tipo === 'presente' ? 'selected' : '' }}>P · Presente</option>
                                    <option value="tarde" {{ $tipo === 'tarde' ? 'selected' : '' }}>T · Tarde</option>
                                    <option value="ausencia_justificada" {{ in_array($tipo, ['ausencia_justificada', 'justificado'], true) ? 'selected' : '' }}>J · Justificado</option>
                                    <option value="ausencia_injustificada" {{ in_array($tipo, ['ausencia_injustificada', 'ausente'], true) ? 'selected' : '' }}>I · Ausente</option>
                                    <option value="feriado" {{ $tipo === 'feriado' ? 'selected' : '' }}>F · Feriado</option>
                                    <option value="sin_clases" {{ $tipo === 'sin_clases' ? 'selected' : '' }}>S · Sin clases</option>
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
            <div class="asist-sticky-save mt-3">
                <button type="submit" class="btn btn-primary btn-lg w-100 w-md-auto">Guardar matriz</button>
                <span class="text-muted small">Los cambios no se guardan hasta tocar este botón.</span>
            </div>
            @endif
        </form>
        @push('scripts')
        <script>
        (function () {
            var form = document.getElementById('asistencias-matrix-form');
            if (!form) return;

            var bulkTipo = document.getElementById('asist-bulk-tipo');
            var cellClassByTipo = {
                '': '',
                presente: 'asist-cell-p',
                tarde: 'asist-cell-t',
                ausencia_justificada: 'asist-cell-j',
                ausencia_injustificada: 'asist-cell-i',
                feriado: 'asist-cell-f',
                sin_clases: 'asist-cell-s'
            };
            var letraByTipo = {
                '': '—',
                presente: 'P',
                tarde: 'T',
                ausencia_justificada: 'J',
                ausencia_injustificada: 'I',
                feriado: 'F',
                sin_clases: 'S'
            };

            function paintCell(select) {
                var td = select.closest('td');
                if (!td) return;
                td.classList.remove('asist-cell-p', 'asist-cell-t', 'asist-cell-j', 'asist-cell-i', 'asist-cell-f', 'asist-cell-s');
                var cls = cellClassByTipo[select.value] || '';
                if (cls) td.classList.add(cls);
                select.dataset.letra = letraByTipo[select.value] || '—';
            }

            function applyToSelects(selects, tipoOverride) {
                var tipo = typeof tipoOverride === 'string' ? tipoOverride : (bulkTipo ? bulkTipo.value : '');
                selects.forEach(function (sel) {
                    sel.value = tipo;
                    paintCell(sel);
                });
            }

            form.querySelectorAll('.asistencia-cell-select').forEach(function (sel) {
                sel.addEventListener('change', function () { paintCell(sel); });
            });

            var btnAll = document.getElementById('asist-bulk-all');
            if (btnAll) {
                btnAll.addEventListener('click', function () {
                    applyToSelects(Array.from(form.querySelectorAll('.asistencia-cell-select')));
                });
            }

            var btnPresentes = document.getElementById('asist-bulk-presentes');
            if (btnPresentes) {
                btnPresentes.addEventListener('click', function () {
                    if (bulkTipo) bulkTipo.value = 'presente';
                    applyToSelects(Array.from(form.querySelectorAll('.asistencia-cell-select')), 'presente');
                });
            }

            form.querySelectorAll('.asist-bulk-col').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var fecha = btn.getAttribute('data-fecha');
                    if (!fecha) return;
                    applyToSelects(Array.from(form.querySelectorAll('.asistencia-cell-select[data-fecha="' + fecha + '"]')));
                });
            });

            var btnCopy = document.getElementById('asist-copy-prev');
            if (btnCopy) {
                btnCopy.addEventListener('click', function () {
                    var fechas = [];
                    form.querySelectorAll('.asist-bulk-col').forEach(function (b) {
                        var f = b.getAttribute('data-fecha');
                        if (f && fechas.indexOf(f) === -1) fechas.push(f);
                    });
                    for (var i = 1; i < fechas.length; i++) {
                        var prev = fechas[i - 1];
                        var cur = fechas[i];
                        var prevSelects = Array.from(form.querySelectorAll('.asistencia-cell-select[data-fecha="' + prev + '"]'));
                        var curSelects = Array.from(form.querySelectorAll('.asistencia-cell-select[data-fecha="' + cur + '"]'));
                        curSelects.forEach(function (sel, idx) {
                            if (!prevSelects[idx]) return;
                            sel.value = prevSelects[idx].value;
                            paintCell(sel);
                        });
                    }
                });
            }
        })();
        </script>
        @endpush
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
