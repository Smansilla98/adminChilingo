@extends('layouts.diseno-editor')

@section('title', $diseno->exists ? 'Editar: '.$diseno->titulo : 'Nuevo diseño')

@push('vite')
@vite(['resources/js/diseno-canvas.js'])
@endpush

@section('content')
<form method="POST"
      action="{{ $diseno->exists ? route('disenos.update', $diseno) : route('disenos.store') }}"
      id="disenoForm"
      class="diseno-studio{{ empty($canEdit) && $diseno->exists ? ' diseno-readonly' : '' }}"
      @if(empty($canEdit) && $diseno->exists) data-readonly="1" @endif>
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
            <nav class="diseno-menu" aria-label="Menú del editor" id="disenoMenuBar">
                <div class="diseno-menu-group">
                    <button type="button" class="diseno-menu-item" data-menu-toggle="archivo" aria-expanded="false" aria-haspopup="true">Archivo</button>
                    <div class="diseno-menu-drop" data-menu-panel="archivo" hidden>
                        <button type="button" class="diseno-menu-option" data-menu-action="save"><i class="bi bi-cloud-check"></i> Guardar</button>
                        <button type="button" class="diseno-menu-option" data-menu-action="export"><i class="bi bi-download"></i> Exportar…</button>
                        <hr class="diseno-menu-sep">
                        <a href="{{ route('disenos.create') }}" class="diseno-menu-option"><i class="bi bi-file-earmark-plus"></i> Diseño nuevo</a>
                        <a href="{{ route('disenos.index') }}" class="diseno-menu-option"><i class="bi bi-folder2-open"></i> Abrir diseños…</a>
                    </div>
                </div>
                <div class="diseno-menu-group">
                    <button type="button" class="diseno-menu-item" data-menu-toggle="editar" aria-expanded="false" aria-haspopup="true">Editar</button>
                    <div class="diseno-menu-drop" data-menu-panel="editar" hidden>
                        <button type="button" class="diseno-menu-option" data-menu-action="undo"><i class="bi bi-arrow-counterclockwise"></i> Deshacer <span class="diseno-menu-kbd">Ctrl+Z</span></button>
                        <button type="button" class="diseno-menu-option" data-menu-action="redo"><i class="bi bi-arrow-clockwise"></i> Rehacer <span class="diseno-menu-kbd">Ctrl+Y</span></button>
                        <hr class="diseno-menu-sep">
                        <button type="button" class="diseno-menu-option" data-menu-action="duplicate"><i class="bi bi-copy"></i> Duplicar <span class="diseno-menu-kbd">Ctrl+D</span></button>
                        <button type="button" class="diseno-menu-option" data-menu-action="delete"><i class="bi bi-trash"></i> Eliminar <span class="diseno-menu-kbd">Supr</span></button>
                        <hr class="diseno-menu-sep">
                        <button type="button" class="diseno-menu-option" data-menu-action="front"><i class="bi bi-layer-forward"></i> Traer al frente</button>
                        <button type="button" class="diseno-menu-option" data-menu-action="back"><i class="bi bi-layer-backward"></i> Enviar atrás</button>
                    </div>
                </div>
                <div class="diseno-menu-group">
                    <button type="button" class="diseno-menu-item" data-menu-toggle="vista" aria-expanded="false" aria-haspopup="true">Vista</button>
                    <div class="diseno-menu-drop" data-menu-panel="vista" hidden>
                        <button type="button" class="diseno-menu-option" data-menu-action="zoom-in"><i class="bi bi-zoom-in"></i> Acercar</button>
                        <button type="button" class="diseno-menu-option" data-menu-action="zoom-out"><i class="bi bi-zoom-out"></i> Alejar</button>
                        <button type="button" class="diseno-menu-option" data-menu-action="zoom-fit"><i class="bi bi-arrows-fullscreen"></i> Ajustar a pantalla</button>
                        <button type="button" class="diseno-menu-option" data-menu-action="zoom-100"><i class="bi bi-aspect-ratio"></i> Zoom 100%</button>
                        <hr class="diseno-menu-sep">
                        <button type="button" class="diseno-menu-option" data-menu-action="panel-plantillas"><i class="bi bi-layout-wtf"></i> Plantillas</button>
                        <button type="button" class="diseno-menu-option" data-menu-action="panel-biblioteca"><i class="bi bi-images"></i> Biblioteca</button>
                        <button type="button" class="diseno-menu-option" data-menu-action="panel-elementos"><i class="bi bi-bounding-box"></i> Elementos</button>
                        <button type="button" class="diseno-menu-option" data-menu-action="panel-marca"><i class="bi bi-droplet-half"></i> Paleta de marca</button>
                    </div>
                </div>
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
                <i class="bi bi-download"></i> Exportar
            </button>
            @if(!empty($canEdit) || !$diseno->exists)
            <button type="submit" class="diseno-btn diseno-btn-primary">
                <i class="bi bi-cloud-check"></i> Guardar
            </button>
            @else
            <span class="diseno-readonly-badge" title="Solo lectura: plantilla de estudio o sin permiso de edición">Solo lectura</span>
            @endif
        </div>
    </header>

    <div class="diseno-body" id="disenoApp"
         data-ancho="{{ old('ancho', $diseno->ancho) }}"
         data-alto="{{ old('alto', $diseno->alto) }}"
         data-formato="{{ old('formato', $diseno->formato) }}"
         data-upload-url="{{ $uploadUrl ?? route('disenos.medios.store') }}"
         data-biblioteca-api="{{ $bibliotecaApiUrl ?? route('disenos.biblioteca.items') }}"
         data-kit-upload-url="{{ $kitUploadUrl ?? route('disenos.kit.store') }}"
         data-kit-list-url="{{ $kitListUrl ?? route('disenos.kit.index') }}"
         data-kit-destroy-url="{{ url('disenos/kit') }}"
         data-can-manage-kit="{{ !empty($canManageKit) ? '1' : '0' }}">
        <script type="application/json" id="disenoInitialJson">@json($diseno->canvas_json)</script>
        <script type="application/json" id="disenoBrandAssets">@json($brandAssets ?? [])</script>

        <aside class="diseno-rail" aria-label="Herramientas">
            <button type="button" class="diseno-rail-btn active" data-panel="select" title="Seleccionar">
                <i class="bi bi-cursor"></i><span>Mover</span>
            </button>
            <button type="button" class="diseno-rail-btn" data-panel="plantillas" title="Plantillas">
                <i class="bi bi-layout-wtf"></i><span>Plantillas</span>
            </button>
            <button type="button" class="diseno-rail-btn" data-panel="biblioteca" title="Biblioteca">
                <i class="bi bi-images"></i><span>Biblioteca</span>
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
                <p class="diseno-hint">Hacé clic en el lienzo para mover y redimensionar. Flechas = 1 px · Shift+flechas = 10 px. Guías al alinear.</p>
                <div class="diseno-quick-actions">
                    <button type="button" class="diseno-chip" data-action="duplicate"><i class="bi bi-copy"></i> Duplicar</button>
                    <button type="button" class="diseno-chip" data-action="delete"><i class="bi bi-trash"></i> Borrar</button>
                    <button type="button" class="diseno-chip" data-action="front"><i class="bi bi-layer-forward"></i> Adelante</button>
                    <button type="button" class="diseno-chip" data-action="back"><i class="bi bi-layer-backward"></i> Atrás</button>
                </div>
            </div>
            <div class="diseno-drawer-panel d-none" data-drawer="plantillas">
                <h3 class="diseno-drawer-title">Tamaño y plantillas</h3>
                <p class="diseno-hint">Presets de redes, print y merch. Cambiar tamaño puede vaciar el lienzo.</p>
                <div class="diseno-template-grid" id="disenoTemplateGrid"></div>
            </div>
            <div class="diseno-drawer-panel d-none" data-drawer="biblioteca">
                {{-- Se rellena por JS (initBibliotecaPanel) --}}
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
                <h4 class="diseno-drawer-subtitle">Alineación</h4>
                <div class="diseno-quick-actions">
                    <button type="button" class="diseno-chip" data-action="align-left" title="Izquierda"><i class="bi bi-text-left"></i></button>
                    <button type="button" class="diseno-chip" data-action="align-center" title="Centro"><i class="bi bi-text-center"></i></button>
                    <button type="button" class="diseno-chip" data-action="align-right" title="Derecha"><i class="bi bi-text-right"></i></button>
                </div>
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
                <h4 class="diseno-drawer-subtitle">Fondos rápidos</h4>
                <div class="diseno-quick-actions">
                    <button type="button" class="diseno-chip" data-action="bg-white">Blanco</button>
                    <button type="button" class="diseno-chip" data-action="bg-black">Negro</button>
                    <button type="button" class="diseno-chip" data-action="bg-accent">Naranja</button>
                    <button type="button" class="diseno-chip" data-action="bg-cream">Crema</button>
                </div>
                <h4 class="diseno-drawer-subtitle">Bloques de flyer</h4>
                <button type="button" class="diseno-add-btn" data-action="flyer-banner">
                    <i class="bi bi-layout-sidebar-inset"></i> Franja superior + título
                </button>
                <button type="button" class="diseno-add-btn" data-action="flyer-cta">
                    <i class="bi bi-cursor-fill"></i> Botón CTA
                </button>
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
                    <small>Se sube a Storage (JPG, PNG, WebP, SVG · máx. 8 MB)</small>
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
                <span class="diseno-status-hint" title="Atajos de teclado">Ctrl+Z deshacer · Ctrl+Y rehacer · Ctrl+D duplicar · Supr borrar</span>
                <span id="disenoZoomLabel">100%</span>
                <input type="range" id="disenoZoomRange" min="15" max="200" value="100" aria-label="Zoom del lienzo">
                <button type="button" class="diseno-btn diseno-btn-ghost diseno-btn-sm" id="disenoZoomFit" title="Ajustar a pantalla" aria-label="Ajustar lienzo a la pantalla">
                    <i class="bi bi-arrows-fullscreen" aria-hidden="true"></i>
                </button>
            </footer>
        </main>

        <aside class="diseno-inspector">
            <section class="diseno-inspector-section">
                <div class="diseno-inspector-head">
                    <h3 class="diseno-drawer-title">Capas</h3>
                    <div class="diseno-layer-tools">
                        <button type="button" class="diseno-btn diseno-btn-ghost diseno-btn-sm" id="disenoLayerGroup" title="Agrupar selección">Grupo</button>
                        <button type="button" class="diseno-btn diseno-btn-ghost diseno-btn-sm" id="disenoLayerUngroup" title="Desagrupar">Desagrupar</button>
                    </div>
                </div>
                <p class="diseno-hint">Doble clic renombrar · arrastrá reordenar · Ctrl+G agrupar</p>
                <div id="disenoLayerList" class="diseno-layer-list"></div>
            </section>
            <section class="diseno-inspector-section">
                <h3 class="diseno-drawer-title">Historial</h3>
                <div id="disenoHistoryList" class="diseno-history-list"></div>
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
