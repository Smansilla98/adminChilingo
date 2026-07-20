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
      class="compositor-studio daw-studio">
    @csrf

    <header class="compositor-menubar daw-menubar">
        <div class="compositor-menubar-left">
            <a href="{{ route('programa.toque.show', $programaRitmo) }}" class="compositor-logo" title="Volver al toque">
                <i class="bi bi-grid-3x3-gap-fill"></i>
                <span>ITO Toques</span>
            </a>
            <span class="compositor-doc-title">{{ $programaRitmo->nombre }}</span>
            @if($programaRitmo->autor)
                <span class="compositor-doc-meta">{{ Str::limit($programaRitmo->autor, 40) }}</span>
            @endif
        </div>
        <div class="compositor-menubar-right">
            <button type="button" class="compositor-btn compositor-btn-ghost" data-daw-add-scene>
                <i class="bi bi-plus-lg"></i> Nueva parte
            </button>
            <button type="button" class="compositor-btn compositor-btn-ghost" data-partitura-demo>
                Ejemplo Chilinga
            </button>
            <a href="{{ route('programa.toque.partitura.edit', $programaRitmo) }}" class="compositor-btn compositor-btn-ghost">
                <i class="bi bi-cloud-upload"></i> Subir foto/PDF
            </a>
            <button type="submit" class="compositor-btn compositor-btn-primary">
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

    <div class="daw-workspace programa-partitura-editor" data-partitura-editor data-daw="1">
        <script type="application/json" data-partitura-initial>@json($vex)</script>
        <input type="hidden" name="partitura_vexflow_json" value="{{ old('partitura_vexflow_json', $vexJson) }}" data-partitura-input>

        <aside class="daw-browser" aria-label="Biblioteca">
            <div class="daw-panel-title">Biblioteca</div>
            <p class="daw-browser-intro">Arrastrá un ejercicio a un cuadrito, o hacé doble clic.</p>
            <div data-daw-browser></div>
        </aside>

        <main class="daw-main daw-main--didactic">
            <section class="daw-session-panel">
                <div class="daw-panel-title">
                    Mapa del toque
                    <span class="daw-panel-hint">filas = tambores · columnas = partes (llamada, toque…)</span>
                </div>
                <div data-daw-session></div>
            </section>

            <section class="daw-clip-panel">
                <div class="daw-panel-title">Armar golpes</div>
                <div data-daw-clip></div>
            </section>

            <section class="daw-preview-panel">
                <div class="daw-panel-title">
                    Cómo se ve el toque
                    <button type="button" class="daw-btn daw-btn-inline" data-daw-toggle-staff>
                        Ver pentagrama musical (avanzado)
                    </button>
                </div>
                <div data-daw-map class="daw-didactic-map"></div>
                <div data-daw-staff-wrap hidden>
                    <p class="daw-staff-note">Solo para quienes lean partitura. No hace falta usarlo para cargar el toque.</p>
                    <div data-partitura-preview class="programa-partitura-preview"></div>
                </div>
            </section>
        </main>
    </div>

    <footer class="compositor-statusbar daw-statusbar">
        <span>Modo didáctico · sin teoría musical · Cuadernillo de Toques</span>
        @if($tieneVex)
            <span class="compositor-status-meta">Toque digital guardado</span>
        @endif
        <label class="compositor-status-check">
            <input type="checkbox" name="quitar_partitura_vexflow" value="1" data-partitura-remove @checked(old('quitar_partitura_vexflow'))>
            Quitar al guardar
        </label>
    </footer>
</form>
@endsection
