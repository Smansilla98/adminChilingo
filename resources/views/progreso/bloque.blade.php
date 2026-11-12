@extends('layouts.app')

@section('title', 'Progreso · '.$bloque->nombre)
@section('page-title', 'Progreso por toque')

@push('styles')
<style>
    .pg-tabla th.pg-toque { writing-mode: vertical-rl; transform: rotate(180deg); white-space: nowrap; font-size: .8rem; vertical-align: bottom; padding: .4rem .2rem; }
    .pg-tabla td, .pg-tabla th { text-align: center; vertical-align: middle; }
    .pg-tabla td:first-child, .pg-tabla th:first-child { text-align: left; position: sticky; left: 0; background: var(--bs-body-bg); z-index: 1; min-width: 12rem; }
    .pg-tabla select { min-width: 7.5rem; font-size: .8rem; }
    .pg-tabla select[data-estado="aprobado"] { border-color: var(--bs-success); font-weight: 700; }
    .pg-tabla select[data-estado="en_proceso"] { border-color: var(--bs-warning); }
    .pg-marca { font-size: .75rem; display: block; }
</style>
@endpush

@section('content')
<x-ito.shell-page :title="$bloque->nombre" eyebrow="Progreso por toque" :subtitle="($bloque->sede->nombre ?? 'Sin sede').' · toques de '.$anio.'° año'">
    <x-slot:actions>
        <a href="{{ route('progreso.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Bloques</a>
    </x-slot:actions>

    <form method="GET" class="d-flex gap-2 align-items-end mb-3" aria-label="Año del programa">
        <div>
            <label class="form-label small mb-1" for="pg-anio">Toques de</label>
            <select id="pg-anio" name="anio" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach(\App\Models\ProgramaRitmo::años() as $n => $txt)<option value="{{ $n }}" @selected($n === $anio)>{{ $txt }}</option>@endforeach
            </select>
        </div>
        <noscript><button class="btn btn-sm btn-primary">Ver</button></noscript>
    </form>

    @if($propuestas->isNotEmpty())
        <section class="ito-card p-3 mb-3" aria-labelledby="pg-prop">
            <h2 class="h6" id="pg-prop">Paso de año para confirmar</h2>
            <p class="small text-muted mb-2">Ya tocan con el bloque todos los toques obligatorios de su año. Se avanza por permanencia: confirmalo vos o la coordinación.</p>
            @foreach($propuestas as $p)
                <div class="d-flex flex-wrap gap-2 align-items-center border-top py-2">
                    <span class="flex-grow-1"><strong>{{ $p->alumno->nombre_apellido ?? 'Alumno' }}</strong> · de {{ $p->anio_desde }}° a {{ $p->anio_hasta }}° año</span>
                    @if($puedeEditar)
                        <form method="POST" action="{{ route('progreso.promociones.confirmar', $p) }}">@csrf<button class="btn btn-sm btn-success">Confirmar</button></form>
                        <form method="POST" action="{{ route('progreso.promociones.descartar', $p) }}">@csrf<button class="btn btn-sm btn-outline-secondary">Todavía no</button></form>
                    @endif
                </div>
            @endforeach
        </section>
    @endif

    @if($toques->isEmpty())
        <x-ito.empty title="No hay toques cargados para {{ $anio }}° año" description="Los toques se cargan en Programa." />
    @elseif($alumnos->isEmpty())
        <x-ito.empty title="El bloque no tiene alumnos activos" />
    @else
        <form method="POST" action="{{ route('progreso.bloque.guardar', $bloque) }}">
            @csrf
            <input type="hidden" name="anio" value="{{ $anio }}">
            <div class="ito-card p-2 mb-3 table-responsive">
                <table class="table table-sm pg-tabla mb-0">
                    <caption class="small">Cada celda: en qué anda el alumno con ese toque. Los opcionales llevan (op.).</caption>
                    <thead>
                        <tr>
                            <th scope="col">Alumno</th>
                            @foreach($toques as $t)
                                <th scope="col" class="pg-toque">{{ $t->nombre }}{{ $t->opcional ? ' (op.)' : '' }}</th>
                            @endforeach
                            <th scope="col">Obligatorios que ya toca</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($alumnos as $a)
                            <tr>
                                <th scope="row">
                                    <a href="{{ route('progreso.alumno', $a) }}">{{ $a->nombre_apellido }}</a>
                                    <span class="pg-marca text-muted">Cursa {{ $anios[$a->id] ?? $anio }}° año</span>
                                </th>
                                @foreach($toques as $t)
                                    @php $e = $estados[$a->id][$t->id]['estado'] ?? 'no_empezado'; @endphp
                                    <td>
                                        @if($puedeEditar)
                                            <select class="form-select form-select-sm" name="estados[{{ $a->id }}][{{ $t->id }}]" data-estado="{{ $e }}" aria-label="{{ $a->nombre_apellido }} · {{ $t->nombre }}" onchange="this.dataset.estado=this.value">
                                                @foreach($estadosPosibles as $k => $txt)<option value="{{ $k }}" @selected($k === $e)>{{ $txt }}</option>@endforeach
                                            </select>
                                        @else
                                            {{ $estadosPosibles[$e] }}
                                        @endif
                                    </td>
                                @endforeach
                                <td>{{ $resumen[$a->id]['aprobados'] }} de {{ $resumen[$a->id]['obligatorios'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($puedeEditar)
                <button class="btn btn-primary"><i class="bi bi-save"></i> Guardar cambios</button>
            @endif
        </form>

        @if($puedeEditar)
            <section class="ito-card p-3 mt-4" aria-labelledby="pg-rep">
                <h2 class="h6" id="pg-rep">Registrar un repaso con devolución</h2>
                <p class="small text-muted">Para dejar escrito cómo salió un toque con el bloque. Si lo toca con el bloque, esos toques quedan marcados así.</p>
                <form method="POST" action="{{ route('progreso.evaluaciones.store') }}" class="row g-2">
                    @csrf
                    <input type="hidden" name="bloque_id" value="{{ $bloque->id }}">
                    <div class="col-md-4">
                        <label class="form-label small" for="pg-al">Alumno</label>
                        <select id="pg-al" name="alumno_id" class="form-select form-select-sm" required>
                            @foreach($alumnos as $a)<option value="{{ $a->id }}">{{ $a->nombre_apellido }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small" for="pg-fe">Fecha</label>
                        <input id="pg-fe" type="date" name="fecha" class="form-control form-control-sm" value="{{ now()->toDateString() }}" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small" for="pg-re">Cómo salió</label>
                        <select id="pg-re" name="resultado" class="form-select form-select-sm" required>
                            @foreach($resultados as $k => $txt)<option value="{{ $k }}">{{ $txt }}</option>@endforeach
                        </select>
                    </div>
                    <fieldset class="col-12">
                        <legend class="form-label small">Toques</legend>
                        <div class="d-flex flex-wrap gap-3">
                            @foreach($toques as $t)
                                <label class="form-check-label"><input type="checkbox" class="form-check-input me-1" name="toques[]" value="{{ $t->id }}">{{ $t->nombre }}</label>
                            @endforeach
                        </div>
                    </fieldset>
                    <div class="col-12">
                        <label class="form-label small" for="pg-de">Devolución</label>
                        <textarea id="pg-de" name="devolucion" rows="2" maxlength="4000" class="form-control form-control-sm" placeholder="Qué salió bien y qué conviene seguir practicando"></textarea>
                    </div>
                    <div class="col-12 form-check ms-2">
                        <input type="checkbox" class="form-check-input" id="pg-vi" name="visible_alumno" value="1" checked>
                        <label class="form-check-label small" for="pg-vi">Que el alumno lo vea en la app</label>
                    </div>
                    <div class="col-12"><button class="btn btn-sm btn-primary">Guardar repaso</button></div>
                </form>
            </section>
        @endif
    @endif
</x-ito.shell-page>
@endsection
