@extends('layouts.app')

@section('title', 'Cargar asistencias')
@section('page-title', 'Cargar asistencias')

@section('content')
<div class="card asist-dia-card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span>Elegí bloque y fecha</span>
        @php
            $esProfeSolo = auth()->user()?->isProfesor() && ! auth()->user()?->isAdmin();
            $matrixRoute = $esProfeSolo ? 'profesor.asistencias.matrix' : 'asistencias.index';
        @endphp
        <a href="{{ route($matrixRoute) }}" class="btn btn-sm btn-outline-secondary">Ir a matriz mensual</a>
    </div>
    <div class="card-body">
        @include('partials.form-ayuda-intro', ['text' => 'Primero elegí el bloque y el día. Después marcás presente o ausente a cada alumno.'])
        <form method="GET" class="mb-4">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Bloque</label>
                    <select name="bloque_id" class="form-select" required>
                        <option value="">Elegí bloque…</option>
                        @foreach($bloques as $b)
                        <option value="{{ $b->id }}" {{ (request('bloque_id') == $b->id || (isset($bloque) && $bloque->id == $b->id)) ? 'selected' : '' }}>{{ $b->nombre }} — {{ $b->sede->nombre ?? '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Fecha</label>
                    <input type="date" name="fecha" class="form-control" value="{{ request('fecha', date('Y-m-d')) }}" required>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Continuar</button>
                </div>
            </div>
        </form>

        @if(isset($bloque))
        <hr>
        <form action="{{ auth()->user()?->isAdmin() ? route('asistencias.store') : route('profesor.asistencias.store') }}" method="POST" id="asist-dia-form">
            @csrf
            <input type="hidden" name="bloque_id" value="{{ $bloque->id }}">
            <input type="hidden" name="fecha" value="{{ request('fecha', date('Y-m-d')) }}">

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <p class="text-muted small mb-0">
                    <strong>{{ $bloque->nombre }}</strong>
                    @if($bloque->sede)<span> · {{ $bloque->sede->nombre }}</span>@endif
                    — <strong>{{ request('fecha', date('Y-m-d')) }}</strong>
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-secondary" id="asist-dia-all-presente">Todos presentes</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="asist-dia-all-ausente">Todos ausentes</button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-sm asist-dia-table">
                    <thead>
                        <tr>
                            <th>Alumno</th>
                            <th>Instrumento</th>
                            <th>Tipo de asistencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($bloque->alumnos->where('activo', true) as $alumno)
                        <tr>
                            <td>
                                {{ $alumno->nombre_apellido }}
                                <input type="hidden" name="asistencias[{{ $loop->index }}][alumno_id]" value="{{ $alumno->id }}">
                            </td>
                            <td>{{ $alumno->instrumento_principal }}</td>
                            <td>
                                <select name="asistencias[{{ $loop->index }}][tipo_asistencia]" class="form-select form-select-sm asist-dia-select">
                                    @foreach($tiposAsistencia as $valor => $etiqueta)
                                    <option value="{{ $valor }}" @selected($valor === 'presente')>{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($bloque->alumnos->where('activo', true)->isEmpty())
            <p class="text-warning">No hay alumnos activos en este bloque.</p>
            @else
            <div class="asist-sticky-save">
                <button type="submit" class="btn btn-primary btn-lg w-100 w-md-auto">Guardar asistencias</button>
            </div>
            @endif
        </form>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var form = document.getElementById('asist-dia-form');
    if (!form) return;
    function setAll(tipo) {
        form.querySelectorAll('.asist-dia-select').forEach(function (sel) { sel.value = tipo; });
    }
    var p = document.getElementById('asist-dia-all-presente');
    var a = document.getElementById('asist-dia-all-ausente');
    if (p) p.addEventListener('click', function () { setAll('presente'); });
    if (a) a.addEventListener('click', function () { setAll('ausencia_injustificada'); });
})();
</script>
@endpush
