@php
    $flat = $medios['partitura_flat'] ?? null;
    $tieneFlat = is_array($flat) && ! empty($flat['musicxml']);
    $flatAppId = (string) config('services.flat.embed_app_id', '');
@endphp

@if($tieneFlat)
<div class="card mb-3 border-info">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h3 class="h6 mb-0"><i class="bi bi-music-note-beamed"></i> Partitura interactiva</h3>
        @if(auth()->user()?->isAdmin())
            <a href="{{ route('programa.toque.compositor.edit', $programaRitmo) }}" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-pencil"></i> Editar
            </a>
        @endif
    </div>
    <div class="card-body p-0">
        <div class="programa-flat-viewer-wrap"
             data-flat-viewer
             data-app-id="{{ $flatAppId }}"
             data-user-id="{{ auth()->id() }}">
            <script type="application/json" class="programa-flat-musicxml">@json($flat['musicxml'])</script>
            <div class="programa-flat-viewer" data-flat-container></div>
            <div class="programa-flat-viewer-loading text-muted small p-3">Cargando partitura…</div>
        </div>
        <p class="form-text px-3 pb-3 mb-0">
            Partitura creada con el editor digital. Podés reproducirla y hacer zoom.
            @if(!empty($medios['partitura']['path']))
                También hay PDF o imagen del libro para comparar.
            @endif
        </p>
    </div>
</div>
@endif
