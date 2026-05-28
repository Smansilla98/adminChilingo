@extends('layouts.app')

@section('title', 'Editar: ' . $programaRitmo->nombre)
@section('page-title', 'Editar página del toque')

@section('content')
@php
    $secciones = old('secciones', $programaRitmo->seccionesProfundizacion());
    $enlaces = old('enlaces', $programaRitmo->enlaces ?? [['etiqueta' => '', 'url' => '']]);
    if ($enlaces === []) {
        $enlaces = [['etiqueta' => '', 'url' => '']];
    }
@endphp

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="{{ route('programa.index') }}">Programa</a></li>
        <li class="breadcrumb-item"><a href="{{ route('programa.toque.show', $programaRitmo) }}">{{ $programaRitmo->nombre }}</a></li>
        <li class="breadcrumb-item active">Editar</li>
    </ol>
</nav>

<form action="{{ route('programa.toque.update', $programaRitmo) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    @include('partials.form-ayuda-intro', ['text' => 'Podés ir de a poco: primero nombre y un texto; después videos y archivos más abajo.'])
    <div class="card mb-3">
        <div class="card-header">Datos del toque (en el listado)</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Nombre *</label>
                    <input type="text" name="nombre" class="form-control" value="{{ old('nombre', $programaRitmo->nombre) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Autor / referencia</label>
                    <input type="text" name="autor" class="form-control" value="{{ old('autor', $programaRitmo->autor) }}">
                </div>
                <div class="col-12">
                    <label class="form-label">Notas (ej. opcional 1er o 2do año)</label>
                    <input type="text" name="notas" class="form-control" value="{{ old('notas', $programaRitmo->notas) }}">
                </div>
                <div class="col-md-6">
                    <div class="form-check">
                        <input type="hidden" name="opcional" value="0">
                        <input class="form-check-input" type="checkbox" name="opcional" value="1" id="opcional" {{ old('opcional', $programaRitmo->opcional) ? 'checked' : '' }}>
                        <label class="form-check-label" for="opcional">Toque opcional</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check">
                        <input type="hidden" name="publicado" value="0">
                        <input class="form-check-input" type="checkbox" name="publicado" value="1" id="publicado" {{ old('publicado', $programaRitmo->publicado) ? 'checked' : '' }}>
                        <label class="form-check-label" for="publicado">Visible en el programa</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Textos de la página del toque</div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Resumen (aparece arriba de la página)</label>
                <textarea name="resumen" class="form-control" rows="2" maxlength="2000">{{ old('resumen', $programaRitmo->resumen) }}</textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Introducción / texto principal</label>
                <textarea name="contenido" class="form-control" rows="6">{{ old('contenido', $programaRitmo->contenido) }}</textarea>
                <div class="form-text">Cada párrafo en una línea; dejá una línea en blanco entre párrafos.</div>
            </div>

            <hr>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <label class="form-label mb-0">Apartados del toque</label>
                <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-seccion"><i class="bi bi-plus"></i> Añadir sección</button>
            </div>
            <p class="small text-muted">Podés armar apartados, por ejemplo: de dónde sale el toque, cómo está armado, qué practicar.</p>
            <div id="secciones-wrap" class="d-grid gap-3">
                @foreach($secciones as $i => $sec)
                <div class="border rounded p-3 seccion-item">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="small text-muted">Sección {{ $i + 1 }}</span>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-quitar-seccion" title="Quitar">×</button>
                    </div>
                    <input type="text" name="secciones[{{ $i }}][titulo]" class="form-control form-control-sm mb-2" placeholder="Título de la sección" value="{{ $sec['titulo'] ?? '' }}">
                    <textarea name="secciones[{{ $i }}][contenido]" class="form-control" rows="4" placeholder="Contenido…">{{ $sec['contenido'] ?? '' }}</textarea>
                </div>
                @endforeach
            </div>

            <hr class="my-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <label class="form-label mb-0">Enlaces externos</label>
                <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-enlace"><i class="bi bi-link-45deg"></i> Añadir enlace</button>
            </div>
            <div id="enlaces-wrap" class="d-grid gap-2">
                @foreach($enlaces as $j => $enlace)
                <div class="row g-2 enlace-item">
                    <div class="col-md-4">
                        <input type="text" name="enlaces[{{ $j }}][etiqueta]" class="form-control form-control-sm" placeholder="Etiqueta" value="{{ $enlace['etiqueta'] ?? '' }}">
                    </div>
                    <div class="col">
                        <input type="url" name="enlaces[{{ $j }}][url]" class="form-control form-control-sm" placeholder="https://…" value="{{ $enlace['url'] ?? '' }}">
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-sm btn-outline-danger btn-quitar-enlace">×</button>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    @include('programa.partials.medios-edit')

    <button type="submit" class="btn btn-primary">Guardar</button>
    <a href="{{ route('programa.toque.show', $programaRitmo) }}" class="btn btn-secondary">Cancelar</a>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const wrapSec = document.getElementById('secciones-wrap');
    const wrapEnl = document.getElementById('enlaces-wrap');
    const btnSec = document.getElementById('btn-add-seccion');
    const btnEnl = document.getElementById('btn-add-enlace');

    function reindexSecciones() {
        if (!wrapSec) return;
        wrapSec.querySelectorAll('.seccion-item').forEach(function (el, i) {
            const t = el.querySelector('input[type="text"]');
            const ta = el.querySelector('textarea');
            if (t) t.name = 'secciones[' + i + '][titulo]';
            if (ta) ta.name = 'secciones[' + i + '][contenido]';
        });
    }

    function reindexEnlaces() {
        if (!wrapEnl) return;
        wrapEnl.querySelectorAll('.enlace-item').forEach(function (el, i) {
            const inputs = el.querySelectorAll('input');
            if (inputs[0]) inputs[0].name = 'enlaces[' + i + '][etiqueta]';
            if (inputs[1]) inputs[1].name = 'enlaces[' + i + '][url]';
        });
    }

    if (btnSec && wrapSec) {
        btnSec.addEventListener('click', function () {
            const i = wrapSec.querySelectorAll('.seccion-item').length;
            const div = document.createElement('div');
            div.className = 'border rounded p-3 seccion-item';
            div.innerHTML =
                '<div class="d-flex justify-content-between mb-2"><span class="small text-muted">Sección ' + (i + 1) + '</span>' +
                '<button type="button" class="btn btn-sm btn-outline-danger btn-quitar-seccion">×</button></div>' +
                '<input type="text" name="secciones[' + i + '][titulo]" class="form-control form-control-sm mb-2" placeholder="Título">' +
                '<textarea name="secciones[' + i + '][contenido]" class="form-control" rows="4" placeholder="Contenido…"></textarea>';
            wrapSec.appendChild(div);
            div.querySelector('.btn-quitar-seccion').addEventListener('click', function () {
                div.remove();
                reindexSecciones();
            });
        });
        wrapSec.addEventListener('click', function (e) {
            if (e.target.classList.contains('btn-quitar-seccion')) {
                e.target.closest('.seccion-item')?.remove();
                reindexSecciones();
            }
        });
    }

    if (btnEnl && wrapEnl) {
        btnEnl.addEventListener('click', function () {
            const i = wrapEnl.querySelectorAll('.enlace-item').length;
            const row = document.createElement('div');
            row.className = 'row g-2 enlace-item';
            row.innerHTML =
                '<div class="col-md-4"><input type="text" name="enlaces[' + i + '][etiqueta]" class="form-control form-control-sm" placeholder="Etiqueta"></div>' +
                '<div class="col"><input type="url" name="enlaces[' + i + '][url]" class="form-control form-control-sm" placeholder="https://…"></div>' +
                '<div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger btn-quitar-enlace">×</button></div>';
            wrapEnl.appendChild(row);
            row.querySelector('.btn-quitar-enlace').addEventListener('click', function () {
                row.remove();
                reindexEnlaces();
            });
        });
        wrapEnl.addEventListener('click', function (e) {
            if (e.target.classList.contains('btn-quitar-enlace')) {
                e.target.closest('.enlace-item')?.remove();
                reindexEnlaces();
            }
        });
    }
})();

(function () {
    const tiposRecurso = @json($tiposRecurso);
    const wrapCortes = document.getElementById('cortes-wrap');
    const wrapRecursos = document.getElementById('recursos-wrap');
    const btnCorte = document.getElementById('btn-add-corte');
    const btnRecurso = document.getElementById('btn-add-recurso');

    function optionsTipos(selected) {
        return Object.entries(tiposRecurso).map(function (pair) {
            const sel = pair[0] === selected ? ' selected' : '';
            return '<option value="' + pair[0] + '"' + sel + '>' + pair[1] + '</option>';
        }).join('');
    }

    function reindexCortes() {
        if (!wrapCortes) return;
        wrapCortes.querySelectorAll('.corte-item').forEach(function (el, i) {
            el.querySelectorAll('[name^="cortes["]').forEach(function (input) {
                input.name = input.name.replace(/cortes\[\d+\]/, 'cortes[' + i + ']');
            });
        });
    }

    function reindexRecursos() {
        if (!wrapRecursos) return;
        wrapRecursos.querySelectorAll('.recurso-item').forEach(function (el, i) {
            el.querySelectorAll('[name^="recursos["]').forEach(function (input) {
                input.name = input.name.replace(/recursos\[\d+\]/, 'recursos[' + i + ']');
            });
        });
    }

    if (btnCorte && wrapCortes) {
        btnCorte.addEventListener('click', function () {
            const i = wrapCortes.querySelectorAll('.corte-item').length;
            const div = document.createElement('div');
            div.className = 'border rounded p-3 corte-item';
            div.innerHTML =
                '<div class="d-flex justify-content-between mb-2"><span class="small text-muted">Corte ' + (i + 1) + '</span>' +
                '<button type="button" class="btn btn-sm btn-outline-danger btn-quitar-corte">×</button></div>' +
                '<input type="text" name="cortes[' + i + '][titulo]" class="form-control form-control-sm mb-2" placeholder="Título del corte">' +
                '<input type="text" name="cortes[' + i + '][url]" class="form-control form-control-sm mb-2" placeholder="URL de video (opcional)">' +
                '<input type="file" name="cortes[' + i + '][archivo]" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.webp,.mp4,.webm">';
            wrapCortes.appendChild(div);
        });
        wrapCortes.addEventListener('click', function (e) {
            if (e.target.classList.contains('btn-quitar-corte')) {
                e.target.closest('.corte-item')?.remove();
                reindexCortes();
            }
        });
    }

    if (btnRecurso && wrapRecursos) {
        btnRecurso.addEventListener('click', function () {
            const i = wrapRecursos.querySelectorAll('.recurso-item').length;
            const div = document.createElement('div');
            div.className = 'border rounded p-3 recurso-item';
            div.innerHTML =
                '<div class="d-flex justify-content-between mb-2"><span class="small text-muted">Recurso ' + (i + 1) + '</span>' +
                '<button type="button" class="btn btn-sm btn-outline-danger btn-quitar-recurso">×</button></div>' +
                '<div class="row g-2 mb-2"><div class="col-md-4"><select name="recursos[' + i + '][tipo]" class="form-select form-select-sm">' +
                optionsTipos('enlace') + '</select></div>' +
                '<div class="col-md-8"><input type="text" name="recursos[' + i + '][titulo]" class="form-control form-control-sm" placeholder="Título"></div></div>' +
                '<input type="text" name="recursos[' + i + '][url]" class="form-control form-control-sm mb-2" placeholder="URL">' +
                '<textarea name="recursos[' + i + '][contenido]" class="form-control form-control-sm mb-2" rows="3" placeholder="Texto"></textarea>' +
                '<input type="file" name="recursos[' + i + '][archivo]" class="form-control form-control-sm">';
            wrapRecursos.appendChild(div);
        });
        wrapRecursos.addEventListener('click', function (e) {
            if (e.target.classList.contains('btn-quitar-recurso')) {
                e.target.closest('.recurso-item')?.remove();
                reindexRecursos();
            }
        });
    }
})();
</script>
@endpush
