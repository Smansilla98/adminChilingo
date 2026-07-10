@extends('layouts.compositor-editor')

@section('title', 'Compositor: '.$programaRitmo->nombre)

@push('vite')
@vite(['resources/css/programa-compositor.css', 'resources/js/programa-compositor.js'])
@endpush

@section('content')
@php
    $flat = $medios['partitura_flat'] ?? null;
    $tieneFlat = ! empty($flat['musicxml']);
    $embedHost = request()->getHost();
    $esLocalEmbed = in_array($embedHost, ['localhost', '127.0.0.1'], true)
        || str_ends_with($embedHost, '.localhost')
        || str_ends_with($embedHost, '.test');
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

    @if(!$esLocalEmbed)
        <div class="compositor-alert compositor-alert-warning compositor-setup-alert">
            <div class="compositor-setup-title">
                <i class="bi bi-exclamation-triangle"></i>
                Configuración requerida para producción (Flat.io)
            </div>
            <p class="mb-2">
                Si ves <strong>«Invalid embed referer»</strong>, el dominio actual no está autorizado en tu app de Flat.
            </p>
            <ol class="compositor-setup-steps mb-2">
                <li>Entrá a <a href="https://flat.io/developers/apps" target="_blank" rel="noopener">flat.io/developers/apps</a> y abrí tu app (o creá una nueva).</li>
                <li>En <strong>Embed → Settings → Authorized domains</strong>, agregá exactamente:
                    <code class="compositor-setup-domain">{{ $embedHost }}</code>
                    (sin <code>https://</code> ni barra final).
                </li>
                <li>Copiá el <strong>App ID</strong> de esa misma página y definilo en Railway como variable <code>FLAT_EMBED_APP_ID</code>.</li>
                <li>Redeploy o reiniciá el servicio y recargá esta página.</li>
            </ol>
            @if($flatAppId === '')
                <p class="mb-0 text-danger"><strong>Falta <code>FLAT_EMBED_APP_ID</code></strong> en las variables de entorno del servidor.</p>
            @else
                <p class="mb-0 text-muted">App ID configurado. Si el error persiste, revisá que el dominio autorizado coincida con <code>{{ $embedHost }}</code>.</p>
            @endif
        </div>
    @endif

    <div class="compositor-body"
         id="compositorApp"
         data-app-id="{{ $flatAppId }}"
         data-user-id="{{ auth()->id() }}"
         data-host="{{ $embedHost }}"
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
