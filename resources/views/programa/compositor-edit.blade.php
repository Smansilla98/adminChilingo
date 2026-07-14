@extends('layouts.compositor-editor')

@section('title', 'Compositor: '.$programaRitmo->nombre)

@push('vite')
@vite(['resources/css/programa-compositor.css', 'resources/js/programa-partitura.js'])
@endpush

@section('content')
@php
    $vex = old('partitura_vexflow_json')
        ? json_decode(old('partitura_vexflow_json'), true)
        : ($medios['partitura_vexflow'] ?? null);
    $tieneVex = ! empty($vex['sections']) || ! empty($vex['hits']);
    $vexJson = $vex ? json_encode($vex, JSON_UNESCAPED_UNICODE) : '';
@endphp

<form method="POST"
      action="{{ route('programa.toque.compositor.update', $programaRitmo) }}"
      id="compositorForm"
      class="compositor-studio">
    @csrf

    <header class="compositor-menubar">
        <div class="compositor-menubar-left">
            <a href="{{ route('programa.toque.show', $programaRitmo) }}" class="compositor-logo" title="Volver al toque">
                <i class="bi bi-music-note-beamed"></i>
                <span>ITO Compositor</span>
            </a>
            <span class="compositor-doc-title">{{ $programaRitmo->nombre }}</span>
            @if($programaRitmo->autor)
                <span class="compositor-doc-meta">{{ Str::limit($programaRitmo->autor, 40) }}</span>
            @endif
        </div>
        <div class="compositor-menubar-right">
            <a href="{{ route('programa.toque.partitura.edit', $programaRitmo) }}" class="compositor-btn compositor-btn-ghost">
                <i class="bi bi-cloud-upload"></i> Subir PDF/imagen
            </a>
            <button type="submit" class="compositor-btn compositor-btn-primary" id="compositorSaveBtn">
                <i class="bi bi-cloud-check"></i> Guardar
            </button>
        </div>
    </header>

    @if($errors->any())
        <div class="compositor-alert compositor-alert-danger">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="compositor-body compositor-body--scroll">
        <div class="compositor-editor-pane programa-partitura-editor" data-partitura-editor>
            <script type="application/json" data-partitura-initial>@json($vex)</script>
            <input type="hidden" name="partitura_vexflow_json" value="{{ old('partitura_vexflow_json', $vexJson) }}" data-partitura-input>

            <div class="compositor-toolbar">
                <button type="button" class="compositor-btn compositor-btn-ghost" data-partitura-add-section>
                    <i class="bi bi-plus-lg"></i> Sección
                </button>
                <button type="button" class="compositor-btn compositor-btn-ghost" data-partitura-demo>
                    Ejemplo (Toque de Chilinga)
                </button>
                <button type="button" class="compositor-btn compositor-btn-ghost" data-partitura-clear>
                    Limpiar sección
                </button>
            </div>

            <div class="mb-3" data-partitura-sections></div>

            <p class="compositor-hint">Instrumentos opcionales (ej. Iyesá: Agogó, Palmas)</p>
            <div class="mb-3" data-partitura-optional></div>

            <div class="programa-partitura-legend" aria-hidden="true">
                <span><i class="lg-on"></i> Golpe activo</span>
                <span>× Chapa / dedo</span>
                <span>&gt; Acento</span>
                <span>◇ Palma</span>
                <span>○ Slap</span>
                <span>△ Agudo</span>
                <span>— Tapado / presionado</span>
                <span>Clic: vacío → golpes del instrumento</span>
            </div>

            <div class="programa-partitura-grid-wrap mb-3" data-partitura-grid></div>

            <div class="compositor-preview-label">Vista previa (pentagramas por instrumento)</div>
            <div data-partitura-preview class="programa-partitura-preview"></div>
        </div>
    </div>

    <footer class="compositor-statusbar">
        <span>Editor local VexFlow · nomenclatura del Cuadernillo de Toques</span>
        @if($tieneVex)
            <span class="compositor-status-meta">Hay partitura digital guardada</span>
        @endif
        <label class="compositor-status-check">
            <input type="checkbox" name="quitar_partitura_vexflow" value="1" data-partitura-remove @checked(old('quitar_partitura_vexflow'))>
            Quitar partitura digital al guardar
        </label>
    </footer>
</form>
@endsection
