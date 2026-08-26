@extends('layouts.app')

@section('title', 'Cargar asistencias')
@section('page-title', 'Cargar asistencias')

@section('content')
@php
    $esProfeSolo = auth()->user()?->isProfesor() && ! auth()->user()?->isAdmin();
    $matrixRoute = $esProfeSolo ? 'profesor.asistencias.matrix' : 'asistencias.index';
    $chipsRapidos = [
        'presente' => ['letra' => 'P', 'texto' => 'Presente'],
        'tarde' => ['letra' => 'T', 'texto' => 'Tarde'],
        'ausencia_justificada' => ['letra' => 'J', 'texto' => 'Justificada'],
        'ausencia_injustificada' => ['letra' => 'I', 'texto' => 'Ausente'],
    ];
    $otrosTipos = collect($tiposAsistencia)->except(array_keys($chipsRapidos));
@endphp
<x-ito.shell-page
    title="Cargar asistencias"
    eyebrow="Asistencias"
    subtitle="Elegí bloque y fecha, después marcá cada alumno."
>
    <x-slot:actions>
        <a href="{{ route($matrixRoute) }}" class="btn btn-sm btn-outline-secondary">Ir a planilla del mes</a>
    </x-slot:actions>
        @include('partials.form-ayuda-intro', ['text' => 'Primero elegí el bloque y el día. Después marcá cada alumno con letra y texto (P, T, J o I).'])
        <form method="GET" class="mb-4">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label" for="asist-bloque">Bloque</label>
                    <select id="asist-bloque" name="bloque_id" class="form-select" required>
                        <option value="">Elegí bloque…</option>
                        @foreach($bloques as $b)
                        <option value="{{ $b->id }}" {{ (request('bloque_id') == $b->id || (isset($bloque) && $bloque->id == $b->id)) ? 'selected' : '' }}>{{ $b->nombre }} — {{ $b->sede->nombre ?? '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="asist-fecha">Fecha</label>
                    <input id="asist-fecha" type="date" name="fecha" class="form-control" value="{{ request('fecha', date('Y-m-d')) }}" required>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Continuar</button>
                </div>
            </div>
        </form>

        @if(isset($bloque))
        <hr>
        <form action="{{ request()->routeIs('asistencias.*') ? route('asistencias.store') : route('profesor.asistencias.store') }}" method="POST" id="asist-dia-form">
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

            <ul class="list-unstyled asist-dia-list mb-0">
                @foreach($bloque->alumnos->where('activo', true) as $i => $alumno)
                @php
                    $guardada = ($asistenciasHoy[$alumno->id] ?? null)?->tipo_asistencia;
                    if ($guardada === 'ausente') { $guardada = 'ausencia_injustificada'; }
                    if ($guardada === 'justificado') { $guardada = 'ausencia_justificada'; }
                    $seleccion = $guardada ?: 'presente';
                    $fid = 'asist-'.$alumno->id;
                @endphp
                <li class="asist-dia-row">
                    <div class="asist-dia-who">
                        <div class="asist-dia-name">{{ $alumno->nombre_apellido }}</div>
                        <div class="asist-dia-meta">{{ $alumno->instrumento_principal ?: 'Sin instrumento' }}</div>
                        <input type="hidden" name="asistencias[{{ $i }}][alumno_id]" value="{{ $alumno->id }}">
                    </div>
                    <fieldset class="asist-chips" role="radiogroup" aria-labelledby="{{ $fid }}-label">
                        <legend class="visually-hidden" id="{{ $fid }}-label">Asistencia de {{ $alumno->nombre_apellido }}</legend>
                        @foreach($chipsRapidos as $valor => $chip)
                        <label class="asist-chip asist-chip--{{ $valor }}">
                            <input class="asist-dia-radio" type="radio" name="asistencias[{{ $i }}][tipo_asistencia]" value="{{ $valor }}" @checked($seleccion === $valor)>
                            <span class="asist-chip-letra" aria-hidden="true">{{ $chip['letra'] }}</span>
                            <span class="asist-chip-texto">{{ $chip['texto'] }}</span>
                        </label>
                        @endforeach
                        @foreach($otrosTipos as $valor => $etiqueta)
                        <label class="asist-chip asist-chip--otro">
                            <input class="asist-dia-radio" type="radio" name="asistencias[{{ $i }}][tipo_asistencia]" value="{{ $valor }}" @checked($seleccion === $valor)>
                            <span class="asist-chip-letra" aria-hidden="true">{{ \App\Models\Asistencia::letraTipo($valor) }}</span>
                            <span class="asist-chip-texto">{{ $etiqueta }}</span>
                        </label>
                        @endforeach
                    </fieldset>
                </li>
                @endforeach
            </ul>

            @if($bloque->alumnos->where('activo', true)->isEmpty())
            <p class="text-warning">No hay alumnos activos en este bloque.</p>
            @else
            <div class="asist-sticky-save">
                <button type="submit" class="btn btn-primary btn-lg w-100">Guardar asistencias</button>
            </div>
            @endif
        </form>
        @endif
</x-ito.shell-page>
@endsection

@push('scripts')
<script>
(function () {
    var form = document.getElementById('asist-dia-form');
    if (!form) return;
    function setAll(tipo) {
        form.querySelectorAll('.asist-dia-row').forEach(function (row) {
            var radio = row.querySelector('input.asist-dia-radio[value="' + tipo + '"]');
            if (radio) radio.checked = true;
        });
    }
    var p = document.getElementById('asist-dia-all-presente');
    var a = document.getElementById('asist-dia-all-ausente');
    if (p) p.addEventListener('click', function () { setAll('presente'); });
    if (a) a.addEventListener('click', function () { setAll('ausencia_injustificada'); });
})();
</script>
@endpush
