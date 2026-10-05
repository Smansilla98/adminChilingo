{{--
    Un acontecimiento como escena. El layout sale del material disponible:
    sin fotos → tipográfico · 1 horizontal → hero · 1 vertical → retrato ·
    2-3 → editorial · 4-5 → collage · 6+ → secuencia horizontal.
    $a (ArchivoAcontecimiento con fotos publicadas cargadas) · $indice
--}}
@php
    $fotos = $a->fotos;
    $portada = $a->portada && $a->portada->esPublica() ? $a->portada : $fotos->first();
    $resto = $portada ? $fotos->reject(fn ($f) => $f->id === $portada->id)->values() : $fotos;
    $n = $fotos->count() ?: ($portada ? 1 : 0);
    $layout = match (true) {
        ! $portada => 'texto',
        $n === 1 && $portada->esVertical() => 'retrato',
        $n === 1 => 'hero',
        $n <= 3 => 'editorial',
        $n <= 5 => 'collage',
        default => 'secuencia',
    };
    $decada = $a->anio ? intdiv($a->anio, 10) * 10 : null;
@endphp
<article class="ar-escena ar-escena--{{ $layout }}" id="ev-{{ $a->slug }}" data-anio="{{ $a->anio }}" data-decada="{{ $decada }}" aria-labelledby="ev-{{ $a->id }}-titulo">
    <header class="ar-escena__cabecera" data-revelar>
        @if($a->anio)<p class="ar-escena__anio ar-mono"><a href="{{ route('archivo.anio', $a->anio) }}">{{ $a->fechaLegible() }}</a></p>@endif
        <h3 class="ar-escena__titulo" id="ev-{{ $a->id }}-titulo"><a href="{{ $a->url() }}">{{ $a->titulo }}</a></h3>
        @if($a->bajada)<p class="ar-escena__bajada">{{ $a->bajada }}</p>@endif
        @if($a->lugar || $a->ciudad)<p class="ar-escena__lugar ar-tenue">{{ collect([$a->lugar, $a->ciudad])->filter()->implode(', ') }}</p>@endif
    </header>

    @switch($layout)
        @case('hero')
            @include('archivo.partials.figura', ['foto' => $portada, 'clase' => 'ar-escena__hero', 'sizes' => '100vw'])
            @break
        @case('retrato')
            @include('archivo.partials.figura', ['foto' => $portada, 'clase' => 'ar-escena__retrato', 'sizes' => '(min-width: 900px) 40vw, 100vw'])
            @break
        @case('editorial')
            <div class="ar-editorial">
                @include('archivo.partials.figura', ['foto' => $portada, 'clase' => 'ar-editorial__principal', 'sizes' => '(min-width: 900px) 62vw, 100vw'])
                @foreach($resto as $f)
                    @include('archivo.partials.figura', ['foto' => $f, 'clase' => 'ar-editorial__secundaria', 'sizes' => '(min-width: 900px) 30vw, 50vw'])
                @endforeach
            </div>
            @break
        @case('collage')
            <div class="ar-collage ar-collage--{{ min($n, 5) }}">
                @include('archivo.partials.figura', ['foto' => $portada, 'clase' => 'ar-collage__item', 'sizes' => '(min-width: 900px) 50vw, 100vw'])
                @foreach($resto as $f)
                    @include('archivo.partials.figura', ['foto' => $f, 'clase' => 'ar-collage__item', 'sizes' => '(min-width: 900px) 25vw, 50vw'])
                @endforeach
            </div>
            @break
        @case('secuencia')
            <div class="ar-secuencia" tabindex="0" role="group" aria-label="Secuencia de fotos de {{ $a->titulo }}">
                @foreach($fotos as $f)
                    @include('archivo.partials.figura', ['foto' => $f, 'clase' => 'ar-secuencia__item', 'sizes' => '(min-width: 900px) 34vw, 82vw'])
                @endforeach
            </div>
            @break
    @endswitch

    @if($a->descripcion || $layout !== 'texto')
        <div class="ar-escena__texto" data-revelar>
            @if($a->descripcion)<p>{{ $a->descripcion }}</p>@endif
            <a class="ar-enlace" href="{{ $a->url() }}">Ver la historia completa <span aria-hidden="true">→</span></a>
        </div>
    @endif
</article>
