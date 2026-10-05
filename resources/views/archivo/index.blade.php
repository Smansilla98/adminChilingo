@extends('layouts.archivo')

@section('body-class', 'archivo--portada')

@section('content')
<section class="ar-portada" aria-labelledby="portada-titulo" @if($portada) style="--ar-color: {{ $portada->color ?: '#1d1b19' }}" @endif>
    @if($portada)
        <div class="ar-portada__foto" aria-hidden="true">
            <img src="{{ $portada->imagenUrl(1200) }}" srcset="{{ $portada->srcset() }}" sizes="100vw" alt="" fetchpriority="high" decoding="async">
        </div>
    @endif
    <div class="ar-portada__texto">
        <p class="ar-sobretitulo ar-mono">Archivo histórico · {{ number_format($linea['total_fotos'], 0, ',', '.') }} fotografías</p>
        <h1 class="ar-portada__titulo" id="portada-titulo">La memoria de <em>La Chilinga</em>, en el tiempo.</h1>
        <p class="ar-portada__bajada">Tambores, calles, escenarios y generaciones. Recorré la historia a través de las fotos de quienes la vivieron.</p>
        <div class="ar-portada__acciones">
            <a class="ar-boton" href="#relato">Recorrer la historia <span aria-hidden="true">↓</span></a>
            <a class="ar-boton ar-boton--linea" href="{{ route('archivo.historia') }}">Modo historia</a>
        </div>
    </div>
    @if($portada)
        <p class="ar-portada__credito ar-mono">{{ $portada->anio }} · {{ $portada->tituloVisible() }}</p>
    @endif
</section>

<div class="ar-cuerpo" id="relato">
    @include('archivo.partials.linea', ['linea' => $linea])

    <div class="ar-relato">
        @forelse($capitulos as $i => $capitulo)
            <section class="ar-capitulo" id="cap-{{ $capitulo->slug }}" data-anio="{{ $capitulo->anio_desde }}" data-decada="{{ $capitulo->anio_desde ? intdiv($capitulo->anio_desde, 10) * 10 : '' }}" aria-labelledby="cap-{{ $capitulo->id }}">
                <header class="ar-capitulo__cabecera" data-revelar>
                    <p class="ar-capitulo__numero ar-mono">Capítulo {{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}@if($capitulo->periodo()) · {{ $capitulo->periodo() }}@endif</p>
                    <h2 class="ar-capitulo__titulo" id="cap-{{ $capitulo->id }}"><a href="{{ $capitulo->url() }}">{{ $capitulo->titulo }}</a></h2>
                    @if($capitulo->bajada)<p class="ar-capitulo__bajada">{{ $capitulo->bajada }}</p>@endif
                </header>
                @if($capitulo->acontecimientos->isEmpty() && $capitulo->portada?->esPublica())
                    @include('archivo.partials.figura', ['foto' => $capitulo->portada, 'clase' => 'ar-escena__hero', 'sizes' => '100vw'])
                @endif
                @foreach($capitulo->acontecimientos as $a)
                    @include('archivo.partials.acontecimiento', ['a' => $a])
                @endforeach
                <p class="ar-capitulo__pie"><a class="ar-enlace" href="{{ $capitulo->url() }}">Todo el capítulo · {{ $capitulo->fotos_publicadas_count }} fotos <span aria-hidden="true">→</span></a></p>
            </section>
        @empty
            @if($sueltos->isEmpty() && $destacadas->isEmpty())
                <section class="ar-vacio">
                    <p class="ar-sobretitulo ar-mono">El archivo está empezando</p>
                    <h2>Todavía no hay historia publicada.</h2>
                    <p>¿Tenés fotos de La Chilinga? Subilas y ayudanos a reconstruir esta historia.</p>
                    <a class="ar-boton" href="{{ route('archivo.aportar') }}">Compartir un recuerdo</a>
                </section>
            @endif
        @endforelse

        @if($sueltos->isNotEmpty())
            <section class="ar-capitulo" aria-labelledby="otros-momentos">
                <header class="ar-capitulo__cabecera" data-revelar>
                    <p class="ar-capitulo__numero ar-mono">Más momentos</p>
                    <h2 class="ar-capitulo__titulo" id="otros-momentos">Otras historias del archivo</h2>
                </header>
                @foreach($sueltos as $a)
                    @include('archivo.partials.acontecimiento', ['a' => $a])
                @endforeach
            </section>
        @endif

        @if($destacadas->isNotEmpty())
            <section class="ar-capitulo" aria-labelledby="destacadas">
                <header class="ar-capitulo__cabecera">
                    <h2 class="ar-capitulo__titulo" id="destacadas">Del archivo</h2>
                </header>
                <div class="ar-mosaico">
                    @foreach($destacadas as $f)
                        @include('archivo.partials.figura', ['foto' => $f, 'sizes' => '(min-width: 900px) 33vw, 50vw', 'epigrafe' => true])
                    @endforeach
                </div>
            </section>
        @endif

        <section class="ar-llamado" aria-labelledby="llamado">
            <p class="ar-sobretitulo ar-mono">Archivo colectivo</p>
            <h2 id="llamado">¿Estuviste ahí?</h2>
            <p>Si tenés fotos, afiches, entradas o recortes de La Chilinga, sumalos al archivo. Cada aporte se revisa antes de publicarse y conserva quién lo compartió.</p>
            <a class="ar-boton" href="{{ route('archivo.aportar') }}">Compartir un recuerdo</a>
        </section>
    </div>
</div>
@endsection
