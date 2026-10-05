@extends('layouts.archivo')

@section('content')
<article class="ar-acontecimiento" data-anio="{{ $a->anio }}">
    <header @class(['ar-cabecera-foto', 'ar-cabecera-foto--sin' => ! $portada])>
        @if($portada)
            <img class="ar-cabecera-foto__img" src="{{ $portada->imagenUrl(1200) }}" srcset="{{ $portada->srcset() }}" sizes="100vw" alt="{{ $portada->textoAlternativo() }}" fetchpriority="high">
        @endif
        <div class="ar-cabecera-foto__texto">
            <p class="ar-sobretitulo ar-mono">
                <a href="{{ route('archivo.index') }}">Archivo</a>
                @if($a->capitulo?->publicado) · <a href="{{ $a->capitulo->url() }}">{{ $a->capitulo->titulo }}</a>@endif
            </p>
            @if($a->anio)<p class="ar-cabecera-foto__anio ar-mono"><a href="{{ route('archivo.anio', $a->anio) }}">{{ $a->fechaLegible() }}</a></p>@endif
            <h1 class="ar-cabecera-foto__titulo">{{ $a->titulo }}</h1>
            @if($a->bajada)<p class="ar-cabecera-foto__bajada">{{ $a->bajada }}</p>@endif
        </div>
    </header>

    <div class="ar-relato ar-relato--angosto">
        <dl class="ar-datos">
            @if($lugar = collect([$a->lugar, $a->ciudad, $a->pais])->filter()->implode(', '))<div><dt>Lugar</dt><dd>{{ $lugar }}</dd></div>@endif
            @if($a->sede)<div><dt>Sede</dt><dd>{{ $a->sede->nombre }}</dd></div>@endif
            <div><dt>Fotos</dt><dd>{{ $a->fotos->count() }}</dd></div>
        </dl>

        @if($a->descripcion)<p class="ar-entradilla" data-revelar>{{ $a->descripcion }}</p>@endif
        @if($a->relato)<div class="ar-prosa" data-revelar>{!! nl2br(e($a->relato)) !!}</div>@endif

        @if($a->fotos->isNotEmpty())
            <section aria-labelledby="fotos-ev" class="ar-bloque">
                <h2 class="ar-subtitulo" id="fotos-ev">Fotografías</h2>
                <div class="ar-mosaico">
                    @foreach($a->fotos as $f)
                        @include('archivo.partials.figura', ['foto' => $f, 'sizes' => '(min-width: 900px) 30vw, 50vw', 'epigrafe' => true])
                    @endforeach
                </div>
            </section>
        @endif

        @php $relacionados = $a->relacionados->where('publicado', true); @endphp
        @if($relacionados->isNotEmpty())
            <section aria-labelledby="rel-ev" class="ar-bloque">
                <h2 class="ar-subtitulo" id="rel-ev">Historias relacionadas</h2>
                <div class="ar-historias">
                    @foreach($relacionados as $r)
                        @include('archivo.partials.tarjeta-historia', ['a' => $r])
                    @endforeach
                </div>
            </section>
        @endif

        <nav class="ar-paso" aria-label="Línea de tiempo">
            @if($anterior)<a href="{{ route('archivo.acontecimiento', $anterior->slug) }}" rel="prev"><span class="ar-tenue">← {{ $anterior->anio }}</span> <strong>{{ $anterior->titulo }}</strong></a>@else<span></span>@endif
            @if($siguiente)<a href="{{ route('archivo.acontecimiento', $siguiente->slug) }}" rel="next"><span class="ar-tenue">{{ $siguiente->anio }} →</span> <strong>{{ $siguiente->titulo }}</strong></a>@endif
        </nav>
    </div>
</article>
@endsection
