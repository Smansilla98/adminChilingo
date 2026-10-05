@extends('layouts.archivo')

@section('body-class', 'archivo--foto')

@section('content')
<article class="ar-ficha" data-anio="{{ $foto->anio }}">
    <div class="ar-ficha__escenario" style="--ar-color: {{ $foto->color ?: '#1d1b19' }}">
        @include('archivo.partials.figura', ['foto' => $foto, 'clase' => 'ar-ficha__figura', 'sizes' => '(min-width: 1100px) 68vw, 100vw', 'eager' => true])
        <nav class="ar-ficha__paso" aria-label="Fotos">
            @if($anterior)<a href="{{ $anterior->url() }}" rel="prev" aria-label="Foto anterior: {{ $anterior->tituloVisible() }}">←</a>@endif
            @if($siguiente)<a href="{{ $siguiente->url() }}" rel="next" aria-label="Foto siguiente: {{ $siguiente->tituloVisible() }}">→</a>@endif
        </nav>
    </div>

    <div class="ar-ficha__texto">
        <p class="ar-sobretitulo ar-mono">
            <a href="{{ route('archivo.index') }}">Archivo</a>
            @if($foto->anio) · <a href="{{ route('archivo.anio', $foto->anio) }}">{{ $foto->anio }}</a>@endif
            @if($foto->acontecimiento?->publicado) · <a href="{{ $foto->acontecimiento->url() }}">{{ $foto->acontecimiento->titulo }}</a>@endif
        </p>
        <h1 class="ar-ficha__titulo">{{ $foto->tituloVisible() }}</h1>
        @if($datos['fecha'] && (string) $datos['fecha'] !== (string) $foto->anio)<p class="ar-mono ar-acento">{{ $datos['fecha'] }}</p>@endif
        @if($foto->descripcion)<p class="ar-entradilla">{{ $foto->descripcion }}</p>@endif
        @if($foto->contexto)
            <div class="ar-prosa ar-prosa--chica">
                <h2 class="ar-etiqueta">Contexto</h2>
                {!! nl2br(e($foto->contexto)) !!}
            </div>
        @endif

        @if(! empty($datos['personas']))
            <section class="ar-ficha__bloque" aria-labelledby="quienes">
                <h2 class="ar-etiqueta" id="quienes">Quiénes aparecen</h2>
                <ul class="ar-personas">
                    @foreach($datos['personas'] as $p)
                        <li><a href="{{ route('archivo.buscar', ['persona' => $p['clave']]) }}">{{ $p['nombre'] }}</a>@if($p['detalle'])<span class="ar-tenue"> — {{ $p['detalle'] }}</span>@endif</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <dl class="ar-datos ar-datos--ficha">
            @if($datos['lugar'])<div><dt>Lugar</dt><dd>{{ $datos['lugar'] }}</dd></div>@endif
            @if($foto->sede)<div><dt>Sede</dt><dd><a href="{{ route('archivo.buscar', ['sede' => $foto->sede_id]) }}">{{ $foto->sede->nombre }}</a></dd></div>@endif
            @if($datos['tipo'])<div><dt>Tipo</dt><dd><a href="{{ route('archivo.buscar', ['tipo' => $foto->tipo]) }}">{{ $datos['tipo'] }}</a></dd></div>@endif
            <div><dt>Fotografía</dt><dd>{{ $foto->fotografo ?: 'Autor/a desconocido/a' }}</dd></div>
            @if($datos['fuente'])<div><dt>Fuente</dt><dd>{{ $datos['fuente'] }}@if($foto->fuente_detalle) · {{ $foto->fuente_detalle }}@endif</dd></div>@endif
            @if($foto->credito)<div><dt>Crédito</dt><dd>{{ $foto->credito }}</dd></div>@endif
            @if($datos['aportante'])<div><dt>Aportado por</dt><dd>{{ $datos['aportante'] }}</dd></div>@endif
            @if($foto->licencia)<div><dt>Licencia</dt><dd>{{ $foto->licencia }}</dd></div>@endif
            @if($datos['camara'])<div><dt>Cámara</dt><dd>{{ $datos['camara'] }}</dd></div>@endif
        </dl>

        @if(! empty($datos['tags']))
            <ul class="ar-tags" aria-label="Etiquetas">
                @foreach($datos['tags'] as $t)
                    <li><a href="{{ route('archivo.buscar', ['tags' => [$t['slug']]]) }}">#{{ $t['nombre'] }}</a></li>
                @endforeach
            </ul>
        @endif

        <p class="ar-ficha__compartir">
            <button type="button" class="ar-boton ar-boton--linea ar-boton--chico" data-compartir data-titulo="{{ $foto->tituloVisible() }}" data-url="{{ $foto->url() }}">Compartir</button>
            @can('editarComoEquipo', $foto)
                <a class="ar-boton ar-boton--linea ar-boton--chico" href="{{ route('archivo.gestion.fotos.edit', $foto) }}">Editar</a>
            @endcan
        </p>
    </div>
</article>

@if($relacionadas->isNotEmpty())
    <section class="ar-relato ar-bloque" aria-labelledby="relacionadas">
        <h2 class="ar-subtitulo" id="relacionadas">{{ $foto->acontecimiento ? 'Del mismo momento' : 'Del mismo año' }}</h2>
        <div class="ar-mosaico">
            @foreach($relacionadas as $f)
                @include('archivo.partials.figura', ['foto' => $f, 'sizes' => '(min-width: 900px) 25vw, 50vw', 'epigrafe' => true])
            @endforeach
        </div>
    </section>
@endif
@endsection
