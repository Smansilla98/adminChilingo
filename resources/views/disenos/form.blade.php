@extends('layouts.diseno-editor')

@section('title', $diseno->exists ? 'Editar: '.$diseno->titulo : 'Nuevo diseño')

@push('vite')
@vite(['resources/js/diseno-canvas.js'])
@endpush

@section('content')
<form method="POST"
      action="{{ $diseno->exists ? route('disenos.update', $diseno) : route('disenos.store') }}"
      id="disenoForm"
      class="diseno-studio">
    @csrf
    @if($diseno->exists)
        @method('PUT')
    @endif

    <header class="diseno-menubar">
        <div class="diseno-menubar-left">
            <a href="{{ route('disenos.index') }}" class="diseno-logo" title="Volver a diseños">
                <i class="bi bi-palette-fill"></i>
                <span>ITO Diseño</span>
            </a>
            <nav class="diseno-menu" aria-label="Menú del editor">
                <button type="button" class="diseno-menu-item" data-menu="archivo">Archivo</button>
                <button type="button" class="diseno-menu-item" data-menu="editar">Editar</button>
                <button type="button" class="diseno-menu-item" data-menu="vista">Vista</button>
            </nav>
            <input type="text"
                   name="titulo"
                   class="diseno-doc-title"
                   value="{{ old('titulo', $diseno->titulo ?: 'Sin título') }}"
                   required
                   aria-label="Título del diseño">
        </div>
        <div class="diseno-menubar-right">
            <span class="diseno-doc-size" id="disenoDocSize">{{ old('ancho', $diseno->ancho) }}×{{ old('alto', $diseno->alto) }}</span>
            <button type="button" class="diseno-btn diseno-btn-ghost" id="disenoUndoBtn" title="Deshacer (Ctrl+Z)">
                <i class="bi bi-arrow-counterclockwise"></i>
            </button>
            <button type="button" class="diseno-btn diseno-btn-ghost" id="disenoRedoBtn" title="Rehacer (Ctrl+Y)">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
            <button type="button" class="diseno-btn diseno-btn-ghost" id="disenoExportBtn">
                <i class="bi bi-download"></i> Exportar PNG
            </button>
            <button type="submit" class="diseno-btn diseno-btn-primary">
                <i class="bi bi-cloud-check"></i> Guardar
            </button>
        </div>
    </header>

    <div class="diseno-body" id="disenoApp"
         data-canvas-json='@json($diseno->canvas_json)'
         data-ancho="{{ old('ancho', $diseno->ancho) }}"
         data-alto="{{ old('alto', $diseno->alto) }}"
         data-formato="{{ old('formato', $diseno->formato) }}">

        <aside class="diseno-rail" aria-label="Herramientas">
            <button type="button" class="diseno-rail-btn active" data-panel="select" title="Seleccionar">
                <i class="bi bi-cursor"></i><span>Mover</span>
            </button>
            <button type="button" class="diseno-rail-btn" data-panel="plantillas" title="Plantillas">
                <i class="bi bi-layout-wtf"></i><span>Plantillas</span>
            </button>
            <button type="button" class="diseno-rail-btn" data-panel="texto" title="Texto">
                <i class="bi bi-type"></i><span>Texto</span>
            </button>
            <button type="button" class="diseno-rail-btn" data-panel="elementos" title="Elementos">
                <i class="bi bi-bounding-box"></i><span>Elementos</span>
            </button>
            <button type="button" class="diseno-rail-btn" data-panel="marca" title="Marca">
                <i class="bi bi-droplet-half"></i><span>Marca</span>
            </button>
            <button type="button" class="diseno-rail-btn" data-panel="subidos" title="Subidos">
                <i class="bi bi-cloud-upload"></i><span>Subidos</span>
            </button>
        </aside>

        <aside class="diseno-drawer" id="disenoDrawer">
            <div class="diseno-drawer-panel" data-drawer="select">
                <h3 class="diseno-drawer-title">Selección</h3>
                <p class="diseno-hint">Hacé clic en el lienzo para mover y redimensionar elementos. Usá las flechas del teclado para ajustar posición.</p>
                <div class="diseno-quick-actions">
                    <button type="button" class="diseno-chip" data-action="duplicate"><i class="bi bi-copy"></i> Duplicar</button>
                    <button type="button" class="diseno-chip" data-action="delete"><i class="bi bi-trash"></i> Borrar</button>
                    <button type="button" class="diseno-chip" data-action="front"><i class="bi bi-layer-forward"></i> Adelante</button>
                    <button type="button" class="diseno-chip" data-action="back"><i class="bi bi-layer-backward"></i> Atrás</button>
                </div>
            </div>
            <div class="diseno-drawer-panel d-none" data-drawer="plantillas">
                <h3 class="diseno-drawer-title">Plantillas</h3>
                <p class="diseno-hint">Elegí el formato del lienzo. Cambiar plantilla vacía el diseño actual.</p>
                <div class="diseno-template-grid" id="disenoTemplateGrid">
                    <button type="button" class="diseno-template-card" data-formato="flyer_feed" data-w="1080" data-h="1350">
                        <span class="diseno-template-ratio ratio-45"></span>
                        <strong>Flyer feed</strong>
                        <small>1080 × 1350</small>
                    </button>
                    <button type="button" class="diseno-template-card" data-formato="historia" data-w="1080" data-h="1920">
                        <span class="diseno-template-ratio ratio-916"></span>
                        <strong>Historia</strong>
                        <small>1080 × 1920</small>
                    </button>
                    <button type="button" class="diseno-template-card" data-formato="afiche_a4" data-w="1240" data-h="1748">
                        <span class="diseno-template-ratio ratio-a4"></span>
                        <strong>Afiche A4</strong>
                        <small>1240 × 1748</small>
                    </button>
                    <button type="button" class="diseno-template-card" data-formato="banner_web" data-w="1200" data-h="628">
                        <span class="diseno-template-ratio ratio-banner"></span>
                        <strong>Banner web</strong>
                        <small>1200 × 628</small>
                    </button>
                </div>
            </div>
            <div class="diseno-drawer-panel d-none" data-drawer="texto">
                <h3 class="diseno-drawer-title">Texto</h3>
                <button type="button" class="diseno-add-btn" data-action="text-heading">
                    <i class="bi bi-type-h1"></i> Título grande
                </button>
                <button type="button" class="diseno-add-btn" data-action="text-sub">
                    <i class="bi bi-type"></i> Subtítulo
                </button>
                <button type="button" class="diseno-add-btn" data-action="text-body">
                    <i class="bi bi-text-paragraph"></i> Párrafo
                </button>
            </div>
            <div class="diseno-drawer-panel d-none" data-drawer="elementos">
                <h3 class="diseno-drawer-title">Elementos</h3>
                <div class="diseno-shape-grid">
                    <button type="button" class="diseno-shape-btn" data-action="rect" title="Rectángulo">
                        <i class="bi bi-square"></i>
                    </button>
                    <button type="button" class="diseno-shape-btn" data-action="circle" title="Círculo">
                        <i class="bi bi-circle"></i>
                    </button>
                    <button type="button" class="diseno-shape-btn" data-action="line" title="Línea">
                        <i class="bi bi-dash-lg"></i>
                    </button>
                </div>
            </div>
            <div class="diseno-drawer-panel d-none" data-drawer="marca">
                <h3 class="diseno-drawer-title">Paleta de marca</h3>
                <p class="diseno-hint">Colores oficiales de La Chilinga / ITO.</p>
                <div class="diseno-palette" id="disenoPalette"></div>
            </div>
            <div class="diseno-drawer-panel d-none" data-drawer="subidos">
                <h3 class="diseno-drawer-title">Subidos</h3>
                <label class="diseno-dropzone" id="disenoDropzone">
                    <input type="file" id="disenoImgInput" accept="image/*" class="d-none" multiple>
                    <i class="bi bi-cloud-arrow-up"></i>
                    <span>Arrastrá una imagen o hacé clic</span>
                    <small>JPG, PNG, WebP</small>
                </label>
            </div>
        </aside>

        <main class="diseno-stage">
            <div class="diseno-stage-inner" id="disenoStageInner">
                <div class="diseno-canvas-frame" id="disenoCanvasFrame">
                    <canvas id="designCanvas"></canvas>
                </div>
            </div>
            <footer class="diseno-statusbar">
                <span id="disenoZoomLabel">100%</span>
                <input type="range" id="disenoZoomRange" min="15" max="200" value="100" aria-label="Zoom">
                <button type="button" class="diseno-btn diseno-btn-ghost diseno-btn-sm" id="disenoZoomFit" title="Ajustar a pantalla">
                    <i class="bi bi-arrows-fullscreen"></i>
                </button>
            </footer>
        </main>

        <aside class="diseno-inspector">
            <section class="diseno-inspector-section">
                <h3 class="diseno-drawer-title">Capas</h3>
                <div id="disenoLayerList" class="diseno-layer-list"></div>
            </section>
            <section class="diseno-inspector-section" id="disenoPropsPanel">
                <h3 class="diseno-drawer-title">Propiedades</h3>
                <p class="diseno-hint">Seleccioná un elemento del lienzo.</p>
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
