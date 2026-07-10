@extends('layouts.compositor-editor')

@section('title', 'Compositor: '.$programaRitmo->nombre)

@push('vite')
@vite(['resources/css/programa-compositor.css', 'resources/js/programa-compositor.js'])
@endpush

@section('content')
@php
    $flat = $medios['partitura_flat'] ?? null;
    $tieneFlat = ! empty($flat['musicxml']);
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

  @if($flatAppId === '' && ! app()->environment('local'))
        <div class="compositor-alert compositor-alert-warning">
            <i class="bi bi-exclamation-triangle"></i>
            Configurá <code>FLAT_EMBED_APP_ID</code> en el servidor para usar el editor en producción.
            <a href="https://flat.io/developers/apps" target="_blank" rel="noopener">Crear app en Flat</a>
        </div>
    @endif

    <div class="compositor-body"
         id="compositorApp"
         data-app-id="{{ $flatAppId }}"
         data-user-id="{{ auth()->id() }}"
         data-mode="edit">
        @if($tieneFlat)
            <script type="application/json" id="compositorInitialXml">@json($flat['musicxml'])</script>
        @endif
        <div id="flatEmbedContainer" class="compositor-embed"></div>
        <div class="compositor-loading" id="compositorLoading">
            <div class="compositor-spinner"></div>
            <span>Cargando editor…</span>
        </div>
    </div>

    <footer class="compositor-statusbar">
        <span>Editor de notación <a href="https://flat.io/es" target="_blank" rel="noopener">Flat</a></span>
        @if($tieneFlat && ! empty($flat['updated_at']))
            <span class="compositor-status-meta">Última edición: {{ \Carbon\Carbon::parse($flat['updated_at'])->diffForHumans() }}</span>
        @endif
        <label class="compositor-status-check">
            <input type="checkbox" name="quitar_partitura_flat" value="1" @checked(old('quitar_partitura_flat'))>
            Quitar partitura digital al guardar
        </label>
    </footer>

    <input type="hidden" name="partitura_flat_musicxml" id="partitura_flat_musicxml" value="">
</form>
@endsection
