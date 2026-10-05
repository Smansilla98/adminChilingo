@extends('layouts.archivo')

@section('content')
<article class="ar-capitulo-pagina">
    <header @class(['ar-cabecera-foto', 'ar-cabecera-foto--sin' => ! ($capitulo->portada?->esPublica())])>
        @if($capitulo->portada?->esPublica())
            <img class="ar-cabecera-foto__img" src="{{ $capitulo->portada->imagenUrl(1200) }}" srcset="{{ $capitulo->portada->srcset() }}" sizes="100vw" alt="{{ $capitulo->portada->textoAlternativo() }}" fetchpriority="high">
        @endif
        <div class="ar-cabecera-foto__texto">
            <p class="ar-sobretitulo ar-mono"><a href="{{ route('archivo.index') }}#cap-{{ $capitulo->slug }}">Archivo</a>@if($numero) · Capítulo {{ str_pad($numero, 2, '0', STR_PAD_LEFT) }}@endif @if($capitulo->periodo()) · {{ $capitulo->periodo() }}@endif</p>
            <h1 class="ar-cabecera-foto__titulo">{{ $capitulo->titulo }}</h1>
            @if($capitulo->bajada)<p class="ar-cabecera-foto__bajada">{{ $capitulo->bajada }}</p>@endif
        </div>
    </header>

    <div class="ar-relato ar-relato--angosto">
        @if($capitulo->descripcion)
            <div class="ar-prosa" data-revelar>{!! nl2br(e($capitulo->descripcion)) !!}</div>
        @endif

        @foreach($acontecimientos as $a)
            @include('archivo.partials.acontecimiento', ['a' => $a])
        @endforeach

        @if($fotos->isNotEmpty())
            <section aria-labelledby="fotos-cap" class="ar-bloque">
                <h2 class="ar-subtitulo" id="fotos-cap">Las fotos del capítulo <span class="ar-tenue">· {{ $fotos->count() }}</span></h2>
                <div class="ar-mosaico">
                    @foreach($fotos as $f)
                        @include('archivo.partials.figura', ['foto' => $f, 'sizes' => '(min-width: 900px) 30vw, 50vw', 'epigrafe' => true])
                    @endforeach
                </div>
            </section>
        @endif

        <nav class="ar-paso" aria-label="Capítulos">
            @if($anterior)<a href="{{ $anterior->url() }}" rel="prev"><span class="ar-tenue">← Capítulo anterior</span> <strong>{{ $anterior->titulo }}</strong></a>@else<span></span>@endif
            @if($siguiente)<a href="{{ $siguiente->url() }}" rel="next"><span class="ar-tenue">Siguiente capítulo →</span> <strong>{{ $siguiente->titulo }}</strong></a>@endif
        </nav>
    </div>
</article>
@endsection
