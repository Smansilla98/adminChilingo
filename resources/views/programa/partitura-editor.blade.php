@extends('layouts.publico')

@section('title', 'Editor · '.$programaRitmo->nombre)
@section('publico-brand', 'Partituras')
@section('body-class', 'pt-shell')
@section('main-class', 'biblio-main--flush')

@section('content')
@php
    $nombreSugerido = $nombreSugerido ?? '';
    $ultima = $ultimaEdicion ?? null;
@endphp
<div
    id="pt-nombre-modal"
    class="pt-nombre-modal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="pt-nombre-title"
>
    <form id="pt-nombre-form" class="pt-nombre-card" autocomplete="name">
        <p class="biblio-eyebrow">{{ !empty($esNueva) ? 'Crear partitura' : 'Antes de editar' }}</p>
        <h2 id="pt-nombre-title">{{ !empty($esNueva) ? 'Vas a crear la partitura de este toque' : '¿Cómo te llamás?' }}</h2>
        <p class="pt-nombre-lead">
            @if(!empty($esNueva))
                Queda una partitura vacía (88 BPM, llamada y toque) lista para escribir o importar MusicXML. Dejá tu nombre: no hace falta cuenta.
            @else
                Queda registro de quién modifica cada partitura. No hace falta crear una cuenta.
            @endif
        </p>
        @if($ultima)
            <p class="pt-nombre-last">Última edición: <strong>{{ $ultima['nombre'] }}</strong>
                @if(!empty($ultima['at']))
                    · {{ \Illuminate\Support\Carbon::parse($ultima['at'])->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                @endif
            </p>
        @endif
        <label class="pt-nombre-label" for="pt-nombre-input">Tu nombre</label>
        <input
            id="pt-nombre-input"
            class="form-control"
            type="text"
            name="editor_nombre"
            maxlength="80"
            minlength="2"
            required
            placeholder="Ej. Lucía Pérez"
            value="{{ $nombreSugerido }}"
            autocomplete="name"
        >
        <p id="pt-nombre-error" class="pt-nombre-error" hidden>Escribí al menos 2 caracteres.</p>
        <div class="pt-nombre-actions">
            <a class="btn btn-outline-secondary" href="{{ route('programa.toque.show', $programaRitmo) }}">Cancelar</a>
            <button type="submit" class="btn btn-primary">{{ !empty($esNueva) ? 'Crear partitura' : 'Entrar al editor' }}</button>
        </div>
    </form>
</div>

<div
    data-partitura-editor
    data-score="{{ json_encode($score, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
    data-save-url="{{ route('programa.toque.editor.guardar', $programaRitmo) }}"
    data-back-url="{{ route('programa.toque.show', $programaRitmo) }}"
    data-parte-url="{{ route('programa.toque.parte', ['programaRitmo' => $programaRitmo, 'instrumento' => '__INST__']) }}"
    data-readonly="0"
    data-editor-nombre=""
    data-upload-ref-url="{{ route('programa.toque.editor.referencia', $programaRitmo) }}"
    data-ref-url="{{ $refUrl ?? '' }}"
    data-ref-tipo="{{ $refTipo ?? 'imagen' }}"
    data-ref-nombre="{{ e($refNombre ?? '') }}"
></div>
@endsection

@push('vite')
@vite(['resources/js/partitura.js'])
@endpush

@push('scripts')
<script>
(function () {
    const KEY = 'chilinga.partituraEditorNombre';
    const modal = document.getElementById('pt-nombre-modal');
    const form = document.getElementById('pt-nombre-form');
    const input = document.getElementById('pt-nombre-input');
    const error = document.getElementById('pt-nombre-error');
    const editorEl = document.querySelector('[data-partitura-editor]');
    if (!modal || !form || !input || !editorEl) return;

    const guardado = (sessionStorage.getItem(KEY) || '').trim();
    if (guardado.length >= 2 && !input.value) input.value = guardado;

    function aplicarNombre(nombre) {
        const n = String(nombre || '').trim();
        editorEl.dataset.editorNombre = n;
        if (window.partituraEditor) window.partituraEditor.editorNombre = n;
        modal.hidden = true;
        modal.classList.add('is-done');
        document.body.classList.remove('pt-nombre-lock');
    }

    function pedir() {
        modal.hidden = false;
        modal.classList.remove('is-done');
        document.body.classList.add('pt-nombre-lock');
        setTimeout(function () { input.focus(); input.select(); }, 40);
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const n = input.value.trim();
        if (n.length < 2) {
            error.hidden = false;
            input.focus();
            return;
        }
        error.hidden = true;
        try { sessionStorage.setItem(KEY, n); } catch (err) {}
        aplicarNombre(n);
    });

    window.addEventListener('partitura:pedir-nombre', pedir);
    pedir();
})();
</script>
@endpush
