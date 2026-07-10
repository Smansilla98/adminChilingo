@extends('layouts.app')

@section('title', $diseno->exists ? 'Editar diseño' : 'Nuevo diseño')
@section('page-title', $diseno->exists ? 'Editar diseño' : 'Nuevo diseño')

@push('vite')
@vite(['resources/js/diseno-canvas.js'])
@endpush

@section('content')
<form method="POST" action="{{ $diseno->exists ? route('disenos.update', $diseno) : route('disenos.store') }}" id="disenoForm" class="diseno-editor-shell">
    @csrf
    @if($diseno->exists)
        @method('PUT')
    @endif

    <header class="diseno-topbar">
        <div class="diseno-topbar-left">
            <a href="{{ route('disenos.index') }}" class="diseno-back">← Diseños</a>
            <div>
                <div class="diseno-kicker">Editor visual</div>
                <input type="text" name="titulo" class="diseno-title-input" value="{{ old('titulo', $diseno->titulo ?: 'Sin título') }}" required aria-label="Título del diseño">
            </div>
        </div>
        <div class="diseno-topbar-right">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="disenoUndoBtn">Deshacer</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="disenoExportBtn">Descargar PNG</button>
            <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
        </div>
    </header>

    <div class="diseno-workspace" id="disenoApp"
         data-canvas-json='@json($diseno->canvas_json)'
         data-ancho="{{ old('ancho', $diseno->ancho) }}"
         data-alto="{{ old('alto', $diseno->alto) }}"
         data-formato="{{ old('formato', $diseno->formato) }}">
        <aside class="diseno-panel">
            <section class="diseno-panel-section">
                <h4>Herramientas</h4>
                <div class="diseno-tool-grid">
                    <button type="button" class="diseno-tool-btn active" data-tool="select">Mover</button>
                    <button type="button" class="diseno-tool-btn" data-action="text">Texto</button>
                    <button type="button" class="diseno-tool-btn" data-action="rect">Rectángulo</button>
                    <button type="button" class="diseno-tool-btn" data-action="circle">Círculo</button>
                    <button type="button" class="diseno-tool-btn" data-action="image">Imagen</button>
                    <button type="button" class="diseno-tool-btn" data-action="delete">Borrar</button>
                </div>
                <input type="file" id="disenoImgInput" accept="image/*" class="d-none">
            </section>
            <section class="diseno-panel-section">
                <h4>Formato</h4>
                <div class="diseno-template-list">
                    <button type="button" class="diseno-template-item" data-formato="flyer_feed" data-w="1080" data-h="1350">Flyer feed <span>1080×1350</span></button>
                    <button type="button" class="diseno-template-item" data-formato="historia" data-w="1080" data-h="1920">Historia <span>1080×1920</span></button>
                    <button type="button" class="diseno-template-item" data-formato="afiche_a4" data-w="1240" data-h="1748">Afiche A4 <span>1240×1748</span></button>
                    <button type="button" class="diseno-template-item" data-formato="banner_web" data-w="1200" data-h="628">Banner web <span>1200×628</span></button>
                </div>
            </section>
            <section class="diseno-panel-section">
                <h4>Paleta de marca</h4>
                <div class="diseno-palette" id="disenoPalette"></div>
            </section>
        </aside>

        <main class="diseno-canvas-area">
            <canvas id="designCanvas"></canvas>
        </main>

        <aside class="diseno-panel diseno-panel-right">
            <section class="diseno-panel-section">
                <h4>Capas</h4>
                <div id="disenoLayerList"></div>
            </section>
            <section class="diseno-panel-section" id="disenoPropsPanel">
                <h4>Propiedades</h4>
                <p class="diseno-hint">Seleccioná un elemento para editarlo.</p>
            </section>
        </aside>
    </div>

    <input type="hidden" name="formato" id="disenoFormato" value="{{ old('formato', $diseno->formato) }}">
    <input type="hidden" name="ancho" id="disenoAncho" value="{{ old('ancho', $diseno->ancho) }}">
    <input type="hidden" name="alto" id="disenoAlto" value="{{ old('alto', $diseno->alto) }}">
    <input type="hidden" name="canvas_json" id="disenoCanvasJson">
    <input type="hidden" name="preview_base64" id="disenoPreviewBase64">
</form>
@endsection
