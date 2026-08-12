@php
    $href = $item->archivoUrl();
    $tags = $item->tags ?? collect();
    $esPng = str_contains(strtolower((string) ($item->mime ?? '')), 'png')
        || str_ends_with(strtolower((string) ($item->nombre_original ?? '')), '.png')
        || str_ends_with(strtolower((string) ($item->path ?? '')), '.png');
    $toqueLabel = $item->etiquetaToqueInstrumento();
    $toqueHref = $item->toque ? route('programa.toque.show', $item->toque) : '';
    $biblioToqueHref = $item->toque
        ? route('biblioteca.index', array_filter([
            'toque' => $item->toque->slug,
            'instrumento' => $item->instrumento ?: null,
        ]))
        : '';
@endphp
<article
    class="biblio-card"
    data-tipo="{{ $item->tipo }}"
    data-biblio-open
    data-biblio-tipo="{{ $item->tipo }}"
    data-biblio-title="{{ e($item->titulo) }}"
    data-biblio-desc="{{ e($item->descripcion ?? '') }}"
    data-biblio-src="{{ $href }}"
    data-biblio-mime="{{ e($item->mime ?? '') }}"
    data-biblio-png="{{ $esPng ? '1' : '0' }}"
    data-biblio-autor="{{ e($item->autor_nombre ?? '') }}"
    data-biblio-tags="{{ e($tags->map(fn ($t) => '#'.$t->nombre)->implode(' ')) }}"
    data-biblio-toque="{{ e($toqueLabel ?? '') }}"
    data-biblio-toque-href="{{ e($toqueHref) }}"
    data-biblio-filter-href="{{ e($biblioToqueHref) }}"
    data-biblio-share-url="{{ $item->permalinkUrl() }}"
    data-biblio-editor-href="{{ e($item->urlPasarAlEditor() ?? '') }}"
    role="button"
    tabindex="0"
    aria-label="Ver {{ $item->titulo }}"
>
    <div class="biblio-card-media {{ $esPng ? 'is-png' : '' }}">
        @if($item->esImagen() && $href)
            <img src="{{ $href }}" alt="{{ $item->titulo }}" loading="lazy">
        @elseif($item->esVideo() && $href)
            <div class="biblio-card-video">
                <video muted playsinline preload="metadata" src="{{ $href }}#t=0.1"></video>
                <span class="biblio-card-play" aria-hidden="true"><i class="bi bi-play-fill"></i></span>
            </div>
        @elseif($item->esAudio() && $href)
            <div class="biblio-card-audio">
                <i class="bi bi-music-note-beamed" aria-hidden="true"></i>
                <span>Audio</span>
            </div>
        @elseif($item->esPdf() && $href)
            <div class="biblio-card-file">
                <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
                <span>PDF</span>
            </div>
        @elseif($item->esEnlace() && $href)
            <div class="biblio-card-file">
                <i class="bi bi-link-45deg" aria-hidden="true"></i>
                <span>Enlace</span>
            </div>
        @else
            <div class="biblio-card-file">
                <i class="bi bi-file-earmark" aria-hidden="true"></i>
                <span>{{ \App\Models\BibliotecaItem::TIPOS[$item->tipo] ?? 'Archivo' }}</span>
            </div>
        @endif
        <span class="biblio-card-type">{{ \App\Models\BibliotecaItem::TIPOS[$item->tipo] ?? $item->tipo }}</span>
        @include('biblioteca.partials.share-button', ['item' => $item, 'variant' => 'icon'])
    </div>
    <div class="biblio-card-body">
        <h2 class="biblio-card-title">{{ $item->titulo }}</h2>
        @if($toqueLabel)
            <a class="biblio-card-toque" href="{{ $biblioToqueHref }}" data-biblio-ignore title="Filtrar por este toque">
                <i class="bi bi-music-note-list" aria-hidden="true"></i> {{ $toqueLabel }}
            </a>
        @endif
        @if($item->descripcion)
            <p class="biblio-card-desc">{{ \Illuminate\Support\Str::limit($item->descripcion, 120) }}</p>
        @endif
        @if($tags->isNotEmpty())
            <div class="biblio-card-tags">
                @foreach($tags as $t)
                    <a href="{{ route('biblioteca.index', ['tag' => $t->slug]) }}" data-biblio-ignore>#{{ $t->nombre }}</a>
                @endforeach
            </div>
        @endif
        <div class="biblio-card-meta">
            @if($item->autor_nombre)
                <span>{{ $item->autor_nombre }}</span>
            @endif
            <time datetime="{{ $item->created_at?->toDateString() }}">{{ $item->created_at?->locale('es')->diffForHumans() }}</time>
        </div>
        @if($item->urlPasarAlEditor())
            <div class="mt-2">
                @include('biblioteca.partials.pasar-editor', ['item' => $item, 'variant' => 'sm'])
            </div>
        @endif
    </div>
</article>
