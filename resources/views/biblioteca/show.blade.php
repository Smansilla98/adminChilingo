@extends('layouts.publico')

@section('title', $item->titulo)
@section('publico-brand', 'Biblioteca')

@php
    $permalink = $item->permalinkUrl();
    $miniatura = $item->miniaturaUrl();
    $desc = $item->descripcion
        ?: ($item->etiquetaToqueInstrumento() ?: 'Material de la biblioteca de La Chilinga');
    $href = $item->archivoUrl();
@endphp

@push('head')
    <link rel="canonical" href="{{ $permalink }}">
    <meta name="description" content="{{ \Illuminate\Support\Str::limit($desc, 160) }}">
    <meta property="og:type" content="{{ $item->esVideo() ? 'video.other' : 'article' }}">
    <meta property="og:site_name" content="{{ config('app.name', 'La Chilinga') }}">
    <meta property="og:title" content="{{ $item->titulo }}">
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit($desc, 180) }}">
    <meta property="og:url" content="{{ $permalink }}">
    <meta property="og:image" content="{{ $miniatura }}">
    <meta property="og:image:secure_url" content="{{ $miniatura }}">
    <meta property="og:image:type" content="image/jpeg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{{ $item->titulo }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $item->titulo }}">
    <meta name="twitter:description" content="{{ \Illuminate\Support\Str::limit($desc, 180) }}">
    <meta name="twitter:image" content="{{ $miniatura }}">
    @if($item->esVideo() && $href && $item->path)
        <meta property="og:video" content="{{ $href }}">
        <meta property="og:video:type" content="{{ $item->mime ?: 'video/mp4' }}">
    @endif
@endpush

@section('content')
<div class="biblio-hero biblio-hero--compact">
    <div>
        <p class="biblio-eyebrow">Biblioteca</p>
        <h1>{{ $item->titulo }}</h1>
        @if($item->etiquetaToqueInstrumento())
            <p class="biblio-lead">{{ $item->etiquetaToqueInstrumento() }}</p>
        @endif
    </div>
    <div class="prog-cta-row">
        @include('biblioteca.partials.share-button', ['item' => $item, 'variant' => 'primary'])
        <a href="{{ route('biblioteca.index') }}" class="btn btn-outline-secondary btn-sm">Ver biblioteca</a>
    </div>
</div>

<div class="biblio-show">
    <div class="biblio-show-media {{ str_contains(strtolower((string) ($item->mime ?? '')), 'png') ? 'is-png' : '' }}">
        @if($item->esImagen() && $href)
            <img src="{{ $href }}" alt="{{ $item->titulo }}">
        @elseif($item->esVideo() && $href)
            <video src="{{ $href }}" controls playsinline preload="metadata" poster="{{ $miniatura }}"></video>
        @elseif($item->esAudio() && $href)
            <div class="biblio-modal-audio">
                <i class="bi bi-music-note-beamed" aria-hidden="true"></i>
                <audio src="{{ $href }}" controls></audio>
            </div>
        @elseif($item->esPdf() && $href)
            <iframe src="{{ $href }}#view=FitH" title="{{ $item->titulo }}"></iframe>
        @elseif($href)
            <div class="biblio-modal-link">
                <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                <a href="{{ $href }}" class="btn btn-primary" target="_blank" rel="noopener">Abrir material</a>
            </div>
        @endif
    </div>
    <div class="biblio-show-info">
        @if($item->descripcion)
            <p class="biblio-lead mb-2">{{ $item->descripcion }}</p>
        @endif
        @if($item->tags->isNotEmpty())
            <div class="biblio-card-tags mb-3">
                @foreach($item->tags as $t)
                    <a href="{{ route('biblioteca.index', ['tag' => $t->slug]) }}">#{{ $t->nombre }}</a>
                @endforeach
            </div>
        @endif
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <p class="small text-muted mb-0">
                @if($item->autor_nombre){{ $item->autor_nombre }} · @endif
                {{ $item->created_at?->locale('es')->isoFormat('D MMM YYYY') }}
            </p>
            <div class="d-flex flex-wrap gap-2">
                @if($item->toque)
                    <a href="{{ route('programa.toque.show', $item->toque) }}" class="btn btn-sm btn-outline-secondary">Ver toque</a>
                @endif
                @if($href)
                    <a href="{{ $href }}" class="btn btn-sm btn-secondary" target="_blank" rel="noopener">Abrir original</a>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/biblioteca-share.js') }}?v=1"></script>
@endpush
