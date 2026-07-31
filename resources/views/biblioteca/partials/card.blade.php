@php
    $href = $item->archivoUrl();
    $tags = $item->tags ?? collect();
@endphp
<article class="biblio-card" data-tipo="{{ $item->tipo }}">
    <div class="biblio-card-media">
        @if($item->esImagen() && $href)
            <a href="{{ $href }}" target="_blank" rel="noopener">
                <img src="{{ $href }}" alt="{{ $item->titulo }}" loading="lazy">
            </a>
        @elseif($item->esVideo() && $href)
            <video controls preload="metadata" src="{{ $href }}"></video>
        @elseif($item->esAudio() && $href)
            <div class="biblio-card-audio">
                <i class="bi bi-music-note-beamed" aria-hidden="true"></i>
                <audio controls preload="metadata" src="{{ $href }}"></audio>
            </div>
        @elseif($item->esPdf() && $href)
            <a class="biblio-card-file" href="{{ $href }}" target="_blank" rel="noopener">
                <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
                <span>Ver PDF</span>
            </a>
        @elseif($item->esEnlace() && $href)
            <a class="biblio-card-file" href="{{ $href }}" target="_blank" rel="noopener">
                <i class="bi bi-link-45deg" aria-hidden="true"></i>
                <span>Abrir enlace</span>
            </a>
        @else
            <div class="biblio-card-file">
                <i class="bi bi-file-earmark" aria-hidden="true"></i>
                <span>{{ \App\Models\BibliotecaItem::TIPOS[$item->tipo] ?? 'Archivo' }}</span>
            </div>
        @endif
        <span class="biblio-card-type">{{ \App\Models\BibliotecaItem::TIPOS[$item->tipo] ?? $item->tipo }}</span>
    </div>
    <div class="biblio-card-body">
        <h2 class="biblio-card-title">{{ $item->titulo }}</h2>
        @if($item->descripcion)
            <p class="biblio-card-desc">{{ \Illuminate\Support\Str::limit($item->descripcion, 120) }}</p>
        @endif
        @if($tags->isNotEmpty())
            <div class="biblio-card-tags">
                @foreach($tags as $t)
                    <a href="{{ route('biblioteca.index', ['tag' => $t->slug]) }}">#{{ $t->nombre }}</a>
                @endforeach
            </div>
        @endif
        <div class="biblio-card-meta">
            @if($item->autor_nombre)
                <span>{{ $item->autor_nombre }}</span>
            @endif
            <time datetime="{{ $item->created_at?->toDateString() }}">{{ $item->created_at?->locale('es')->diffForHumans() }}</time>
        </div>
    </div>
</article>
