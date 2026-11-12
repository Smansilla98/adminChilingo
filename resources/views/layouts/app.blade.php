<!DOCTYPE html>
<html lang="es">
<head>
    @include('layouts.partials.head-ito')
    @stack('vite')
    @stack('styles')
    @auth
    @vite('resources/css/recordatorio-chatbot.css')
    @endauth
</head>
<body>
<a class="ito-skip" href="#contenido-principal">Ir al contenido</a>
<div id="itoA11yLive" class="ito-sr-only" aria-live="polite" aria-atomic="true"></div>
@auth
<div class="shell" id="appShell">
    <button type="button" class="nav-backdrop" id="navBackdrop" aria-label="Cerrar menú" tabindex="-1"></button>

    <aside class="sidebar" id="sidebarNav" aria-label="Menú principal">
        <div class="sidebar-head">
            <a class="sidebar-brand" href="{{ route('dashboard') }}" aria-label="La Chilinga — Inicio">
                <x-brand-logo variant="sidebar" alt="" />
                <span class="sidebar-brand-text">
                    <span class="sidebar-brand-title">La Chilinga</span>
                    <span class="sidebar-brand-sub">Escuela de percusión</span>
                </span>
            </a>
            <button type="button" class="sidebar-collapse-btn" id="sidebarCollapse" aria-label="Contraer menú" aria-pressed="false" title="Contraer menú">
                <i class="bi bi-chevron-bar-left" aria-hidden="true"></i>
            </button>
        </div>

        <nav class="side-nav" aria-label="Navegación principal">
            @include('layouts.partials.sidebar-nav')
        </nav>

        @if(auth()->user()->tieneAccesoModulo('ayuda'))
            <div class="sidebar-foot">
                <a class="sidebar-foot-link" href="{{ route('ayuda') }}" title="Ayuda">
                    <i class="bi bi-life-preserver" aria-hidden="true"></i>
                    <span>Ayuda y guía de uso</span>
                </a>
            </div>
        @endif
    </aside>

    <div class="main">
        @include('layouts.partials.topbar-hub')

        <main class="content" id="contenido-principal" tabindex="-1">
            @if(auth()->user()?->isAdmin() && ! env('PERSISTENT_STORAGE_PATH') && app()->environment('production'))
                <div class="alert alert-warning small">
                    <strong>Almacenamiento:</strong> no hay <code>PERSISTENT_STORAGE_PATH</code> configurado. Los comprobantes y PDFs pueden perderse al redesplegar. Configurá un volumen persistente o S3.
                </div>
            @endif
            @if(session('import_errors') && is_array(session('import_errors')) && count(session('import_errors')) > 0)
                <div class="alert alert-warning">
                    <div class="fw-semibold">Importación: advertencias</div>
                    <ul class="mb-0">
                        @foreach(session('import_errors') as $msg)
                            <li>{{ $msg }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if($errors->any())
                <script type="application/json" id="itoErrores">@json($errors->getMessages())</script>
                <div class="alert alert-danger" role="alert">
                    <div class="fw-semibold mb-1">Revisá los datos marcados</div>
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    @include('layouts.partials.recordatorio-chatbot')
</div>
@endauth

{{-- Avisos flotantes: éxito se cierra solo; errores quedan hasta cerrarlos. --}}
<div class="ito-toasts" id="itoToasts" aria-live="polite">
    @if(session('success'))
        <div class="ito-toast ito-toast--success" role="status" data-autohide="6000">
            <i class="bi bi-check-circle-fill ito-toast-icon" aria-hidden="true"></i>
            <div class="ito-toast-body">{{ session('success') }}</div>
            <button type="button" class="ito-toast-close" aria-label="Cerrar aviso">&times;</button>
        </div>
    @endif
    @if(session('error'))
        <div class="ito-toast ito-toast--danger" role="alert">
            <i class="bi bi-exclamation-octagon-fill ito-toast-icon" aria-hidden="true"></i>
            <div class="ito-toast-body">{{ session('error') }}</div>
            <button type="button" class="ito-toast-close" aria-label="Cerrar aviso">&times;</button>
        </div>
    @endif
    {{-- Errores de operaciones sin formulario propio (borrado protegido, anulaciones, fusiones) --}}
    @foreach(['eliminar', 'pago', 'persona', 'asignacion'] as $claveError)
        @if($errors->has($claveError))
            <div class="ito-toast ito-toast--danger" role="alert">
                <i class="bi bi-exclamation-octagon-fill ito-toast-icon" aria-hidden="true"></i>
                <div class="ito-toast-body">{{ $errors->first($claveError) }}</div>
                <button type="button" class="ito-toast-close" aria-label="Cerrar aviso">&times;</button>
            </div>
        @endif
    @endforeach
</div>

<div id="itoConfirmModal" class="ito-confirm" hidden role="alertdialog" aria-modal="true" aria-labelledby="itoConfirmTitle" aria-describedby="itoConfirmMessage">
    <div class="ito-confirm-backdrop" tabindex="-1"></div>
    <div class="ito-confirm-dialog">
        <button type="button" class="ito-confirm-close" id="itoConfirmClose" aria-label="Cerrar">&times;</button>
        <div class="ito-confirm-icon" aria-hidden="true"><i class="bi bi-exclamation-triangle"></i></div>
        <h2 id="itoConfirmTitle">¿Confirmar acción?</h2>
        <p id="itoConfirmMessage">Revisá antes de continuar.</p>
        <div class="ito-confirm-actions">
            <button type="button" class="btn btn-outline-secondary" id="itoConfirmCancel">Cancelar</button>
            <button type="button" class="btn btn-danger" id="itoConfirmOk">Confirmar</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@vite('resources/js/legacy/ito-shell.js')
@vite('resources/js/legacy/hub-search.js')
@auth
@vite('resources/js/legacy/recordatorio-chatbot.js')
@endauth
@vite('resources/js/legacy/ito-a11y.js')
@vite('resources/js/legacy/ito-nav-progress.js')
@vite('resources/js/legacy/ito-form-steps.js')
@vite('resources/js/legacy/ito-tables.js')
@stack('scripts')
</body>
</html>
